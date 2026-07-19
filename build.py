import os
import re
import json

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
                meta[key.strip()] = value.strip().strip('"\'[]')
    else:
        meta = {"title": "No Title", "date": "Unknown", "categories": "", "tags": ""}
        body = raw_content

    # 本文の簡易HTML構造化（見出し、箇条書きリスト、改行処理）
    body_html = body.strip()
    body_html = re.sub(r'^### (.*?)$', r'<h3>\1</h3>', body_html, flags=re.MULTILINE)
    body_html = re.sub(r'^\d+\.\s+(.*?)$', r'<li>\1</li>', body_html, flags=re.MULTILINE)
    body_html = re.sub(r'((?:<li>.*?</li>\s*)+)', r'<ol>\1</ol>', body_html)
    body_html = '<p>' + body_html.replace('\n\n', '</p><p>').replace('\n', '<br>') + '</p>'
    
    return meta, body_html

def main():
    post_dir = '_posts'
    
    # 金型（共通テンプレート）の読み込み
    with open('template.html', 'r', encoding='utf-8') as f:
        template = f.read()
        
    articles_index = []

    # 1. _posts フォルダ内を年月階層まで再帰的に探索して個別記事を生成
    for root, dirs, files in os.walk(post_dir):
        for filename in files:
            if filename.endswith('.md'):
                md_path = os.path.join(root, filename)
                
                # フォルダ階層の抽出 (例: '2026/07')
                rel_path = os.path.relpath(root, post_dir)
                if rel_path == '.':
                    output_dir = '.'
                    depth_prefix = ''
                else:
                    output_dir = rel_path
                    # 物理階層の深さに応じて「../」を自動計算
                    depth = len(output_dir.split(os.sep))
                    depth_prefix = '../' * depth

                # 出力先フォルダ（2026/07 など）がなければ物理作成
                os.makedirs(output_dir, exist_ok=True)
                
                # Markdownの解析
                meta, body_html = parse_markdown(md_path)
                
                # 個別記事専用の日付・メタ情報タグ
                meta_info_html = f'<time class="post-date-meta">日付: {meta.get("date", "")} | カテゴリ: {meta.get("categories", "")} | タグ: {meta.get("tags", "")}</time>'
                
                # 金型（template.html）への流し込みと個別HTMLの組み立て
                html_content = template
                html_content = html_content.replace('{{RELATIVE_DEPTH}}', depth_prefix)
                html_content = html_content.replace('{{TITLE}}', meta.get('title', ''))
                html_content = html_content.replace('{{META_INFO}}', meta_info_html)
                html_content = html_content.replace('{{BODY}}', body_html)
                
                # HTMLファイルの物理出力（例：2026/07/000000.html）
                html_filename = filename.replace('.md', '.html')
                output_path = os.path.join(output_dir, html_filename)
                with open(output_path, 'w', encoding='utf-8') as f:
                    f.write(html_content)
                
                # Webサイト上での公開URLパスを計算してインデックス用に記録
                web_path = f"./{output_dir}/{html_filename}".replace('\\', '/')
                articles_index.append({
                    "t": meta.get('title', ''),
                    "d": meta.get('date', ''),
                    "p": web_path
                })
                print(f"Compiled: {web_path}")

    # 2. 検索用のインデックスJSONデータをassetsフォルダ内へ保存
    os.makedirs('assets', exist_ok=True)
    with open('assets/articles.json', 'w', encoding='utf-8') as f:
        json.dump(articles_index, f, ensure_ascii=False)
        
    # 3. トップページ（index.html）の生成（個別記事と共通の金型を適用）
    # 3-1. 本文エリアに流し込む記事一覧のHTML（リスト構造）を組み立てる
    list_body_html = '<ul class="archive-list">'
    # 日付（date）の新しい順（降順）にソートして並び替え
    for art in sorted(articles_index, key=lambda x: x['d'], reverse=True):
        list_body_html += f"<li><a href='{art['p']}'>{art['t']}</a><span class='date'>{art['d']}</span></li>"
    list_body_html += "</ul>"
    
    # 3-2. 金型（template.html）へ流し込み（ルート配置のため階層深さプレフィックスは空文字 '' ）
    index_html_content = template
    index_html_content = index_html_content.replace('{{RELATIVE_DEPTH}}', '')
    index_html_content = index_html_content.replace('{{TITLE}}', '最新記事一覧')
    index_html_content = index_html_content.replace('{{META_INFO}}', '') # トップページには日付等のメタ行は不要なため完全消去
    index_html_content = index_html_content.replace('{{BODY}}', list_body_html)
    
    # 3-3. ルート直下に index.html として物理保存
    with open('index.html', 'w', encoding='utf-8') as f:
        f.write(index_html_content)
    print("Compiled: index.html (Unified Layout)")

if __name__ == '__main__':
    main()