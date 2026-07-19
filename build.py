import os
import re
import json
import shutil
from collections import defaultdict

def parse_markdown(filepath):
    """
    Markdownファイルを読み込み、Front Matter（メタデータ）と本文HTMLを分離・抽出する。
    """
    with open(filepath, 'r', encoding='utf-8') as f:
        raw_content = f.read()
    
    meta = {}
    front_matter_match = re.match(r'^---\s*\n(.*?)\n---\s*\n(.*)', raw_content, re.DOTALL)
    
    if front_matter_match:
        front_matter = front_matter_match.group(1)
        body = front_matter_match.group(2)
        for line in front_matter.split('\n'):
            if ':' in line:
                key, value = line.split(':', 1)
                key = key.strip()
                val_str = value.strip()
                
                val_str = re.sub(r'^[\"\'\[ ]+|[\"\'\] ]+$', '', val_str)
                val_str = re.sub(r'\s*[\"\']\s*,\s*[\"\']\s*', ', ', val_str)
                val_str = re.sub(r'\s*,\s*', ', ', val_str)
                
                meta[key] = val_str
    else:
        meta = {"title": "No Title", "date": "Unknown", "categories": "", "tags": ""}
        body = raw_content

    # 本文の簡易HTML構造化（見出し、箇条書きリスト、改行処理）
    body_html = body.strip()
    body_html = re.sub(r'^### (.*?)$', r'<h3>\1</h3>', body_html, flags=re.MULTILINE)
    body_html = re.sub(r'^\* (.*?)$', r'<li>\1</li>', body_html, flags=re.MULTILINE) # 箇条書き対応の強化
    body_html = re.sub(r'((?:<li>.*?</li>\s*)+)', r'<ul>\1</ul>', body_html)
    body_html = '<p>' + body_html.replace('\n\n', '</p><p>').replace('\n', '<br>') + '</p>'
    
    return meta, body_html

def generate_index_page(title, articles, depth_prefix, template, output_filepath):
    """
    指定された記事リストを元に、共通テンプレートを適用した一覧インデックスHTMLを出力する。
    """
    list_body_html = '<ul class="archive-list">'
    for art in sorted(articles, key=lambda x: x['d'], reverse=True):
        list_body_html += f"<li><a href='{depth_prefix}{art['p']}'>{art['t']}</a><span class='date'>{art['d']}</span></li>"
    list_body_html += "</ul>"
    
    index_html_content = template
    index_html_content = index_html_content.replace('{{RELATIVE_DEPTH}}', depth_prefix)
    index_html_content = index_html_content.replace('{{TITLE}}', title)
    index_html_content = index_html_content.replace('{{META_INFO}}', '')
    index_html_content = index_html_content.replace('{{BODY}}', list_body_html)
    
    with open(output_filepath, 'w', encoding='utf-8') as f:
        f.write(index_html_content)

def main():
    post_dir = '_posts'
    
    with open('template.html', 'r', encoding='utf-8') as f:
        template = f.read()
        
    all_articles = []
    yearly_articles = defaultdict(list)
    monthly_articles = defaultdict(list)

    # 1. _posts フォルダ内を再帰的にスキャン
    for root, dirs, files in os.walk(post_dir):
        rel_path = os.path.relpath(root, post_dir)
        
        # 階層深さと相対パスプレフィックスの決定
        if rel_path == '.':
            output_dir = '.'
            depth_prefix = ''
        else:
            output_dir = rel_path
            depth = len(output_dir.split(os.sep))
            depth_prefix = '../' * depth

        for filename in files:
            src_file = os.path.join(root, filename)
            
            # 1-1. Markdownファイルの処理
            if filename.endswith('.md'):
                meta, body_html = parse_markdown(src_file)
                
                # 【プロフィール(profile.md)専用の分岐回路】
                if filename == 'profile.md':
                    html_content = template
                    html_content = html_content.replace('{{RELATIVE_DEPTH}}', '') # ルート配置のため空文字
                    html_content = html_content.replace('{{TITLE}}', meta.get('title', 'プロフィール'))
                    html_content = html_content.replace('{{META_INFO}}', '') # プロフィールに日付等のメタ行は不要
                    html_content = html_content.replace('{{BODY}}', body_html)
                    
                    with open('profile.html', 'w', encoding='utf-8') as f:
                        f.write(html_content)
                    print("Compiled: profile.html (From Markdown)")
                    continue # 通常の記事一覧プロセス（以降の処理）には流さない
                
                # 以下、通常記事のコンパイル処理
                os.makedirs(output_dir, exist_ok=True)
                meta_info_html = f'<time class="post-date-meta">日付: {meta.get("date", "")} | カテゴリ: {meta.get("categories", "")} | タグ: {meta.get("tags", "")}</time>'
                
                html_content = template
                html_content = html_content.replace('{{RELATIVE_DEPTH}}', depth_prefix)
                html_content = html_content.replace('{{TITLE}}', meta.get('title', ''))
                html_content = html_content.replace('{{META_INFO}}', meta_info_html)
                html_content = html_content.replace('{{BODY}}', body_html)
                
                html_filename = filename.replace('.md', '.html')
                output_html_path = os.path.join(output_dir, html_filename)
                with open(output_html_path, 'w', encoding='utf-8') as f:
                    f.write(html_content)
                
                web_path = f"{output_dir}/{html_filename}".replace('\\', '/')
                
                article_data = {
                    "t": meta.get('title', ''),
                    "d": meta.get('date', ''),
                    "p": web_path
                }
                
                all_articles.append(article_data)
                
                date_str = meta.get('date', '0000-00-00')
                if len(date_str) >= 7:
                    year = date_str[0:4]
                    month = date_str[5:7]
                    yearly_articles[year].append(article_data)
                    monthly_articles[f"{year}/{month}"].append(article_data)
                
                print(f"Compiled: {web_path}")
                
            # 1-2. メディアファイルの場合、そのまま公開フォルダへバイナリ等価コピー
            elif not filename.startswith('.'):
                os.makedirs(output_dir, exist_ok=True)
                dst_file = os.path.join(output_dir, filename)
                shutil.copy2(src_file, dst_file)
                print(f"Asset Copied: {dst_file}")

    # 2. 全体検索用JSONデータの書き出し
    os.makedirs('assets', exist_ok=True)
    with open('assets/articles.json', 'w', encoding='utf-8') as f:
        json.dump(all_articles, f, ensure_ascii=False)
        
    # 3. 各種一覧インデックス（アーカイブ）ページの全自動生成
    generate_index_page('最新記事一覧', all_articles, '', template, 'index.html')
    print("Generated: 全体トップページ (index.html)")
    
    for year, articles in yearly_articles.items():
        os.makedirs(year, exist_ok=True)
        target_path = os.path.join(year, 'index.html')
        generate_index_page(f"{year}年 記事一覧", articles, '../', template, target_path)
        print(f"Generated: 年別アーカイブ ({target_path})")
        
    for y_m, articles in monthly_articles.items():
        os.makedirs(y_m, exist_ok=True)
        target_path = os.path.join(y_m, 'index.html')
        generate_index_page(f"{y_m.replace('/', '年')}月 記事一覧", articles, '../../', template, target_path)
        print(f"Generated: 月別アーカイブ ({target_path})")

if __name__ == '__main__':
    main()