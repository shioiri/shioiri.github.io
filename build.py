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
        meta = {"title": "No Title", "date": "none", "categories": "", "tags": ""}
        body = raw_content

    # 本文の簡易HTML構造化（見出し、箇取りリスト、改行処理）
    body_html = body.strip()
    body_html = re.sub(r'^### (.*?)$', r'<h3>\1</h3>', body_html, flags=re.MULTILINE)
    body_html = re.sub(r'^\* (.*?)$', r'<li>\1</li>', body_html, flags=re.MULTILINE)
    body_html = re.sub(r'((?:<li>.*?</li>\s*)+)', r'<ul>\1</ul>', body_html)
    
    # 【バグ修正版】width: 100% と max二重制限の組み合わせにより、ポートレート画像もスマホ幅で確実に追従縮小する回路
    img_replacement = (
        r'<a href="\2" target="_blank" title="クリックで拡大（別タブ）" style="display:inline-block; text-decoration:none; max-width:100%;"> '
        r'<img src="\2" alt="\1" style="max-width:min(100%, 500px); max-height:400px; width:100%; height:auto; '
        r'display:block; margin:20px 0; border:1px solid #ddd; box-shadow:0 2px 4px rgba(0,0,0,0.05); cursor:pointer;">'
        r'</a>'
    )
    body_html = re.sub(r'!\[(.*?)\]\((.*?)\)', img_replacement, body_html)
    
    body_html = '<p>' + body_html.replace('\n\n', '</p><p>').replace('\n', '<br>') + '</p>'
    
    return meta, body_html

def generate_index_page(title, articles, depth_prefix, template, output_filepath, monthly_menu_html, custom_body_html=None):
    """
    共通テンプレートを適用した一覧インデックスHTML（トップやアーカイブ）を出力する。
    """
    list_body_html = ''
    if custom_body_html:
        list_body_html += custom_body_html
        list_body_html += '<h3 style="margin-top:40px; border-left:4px solid #333; padding-left:10px;">新着記事一覧</h3>'

    list_body_html += '<ul class="archive-list">'
    for art in sorted(articles, key=lambda x: x['d'], reverse=True):
        list_body_html += f"<li><a href='{depth_prefix}{art['p']}'>{art['t']}</a><span class='date'>{art['d']}</span></li>"
    list_body_html += "</ul>"
    
    index_html_content = template
    index_html_content = index_html_content.replace('{{RELATIVE_DEPTH}}', depth_prefix)
    index_html_content = index_html_content.replace('{{DYNAMIC_MONTHLY_MENU}}', monthly_menu_html)
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
    
    compiled_posts = []
    top_page_html = None
    top_page_title = '最新記事一覧'

    # _posts フォルダ内を再帰的に走査し、HTML生成とアセットコピーをその場で実行
    for root, dirs, files in os.walk(post_dir):
        rel_path = os.path.relpath(root, post_dir)
        
        if rel_path == '.':
            output_dir = '.'
            depth_prefix = ''
        else:
            output_dir = rel_path
            depth = len(output_dir.split(os.sep))
            depth_prefix = '../' * depth

        for filename in files:
            src_file = os.path.join(root, filename)
            
            if filename.endswith('.md'):
                meta, body_html = parse_markdown(src_file)
                
                if filename == 'top.md':
                    top_page_html = body_html
                    top_page_title = meta.get('title', '最新記事一覧')
                    continue
                
                if filename == 'profile.md':
                    compiled_posts.append({
                        "type": "profile",
                        "meta": meta,
                        "body_html": body_html,
                        "src_file": src_file,
                        "output_dir": output_dir,
                        "depth_prefix": depth_prefix,
                        "filename": filename
                    })
                    continue
                
                html_filename = filename.replace('.md', '.html')
                web_path = f"{output_dir}/{html_filename}".replace('\\', '/')
                date_str = meta.get('date', '').lower().strip()
                
                article_data = {
                    "t": meta.get('title', ''),
                    "d": meta.get('date', ''),
                    "p": web_path
                }
                
                # dateに9999が含まれる固定ページを除外する安全弁
                is_standalone = date_str in ['none', 'n/a', '', 'unknown'] or '9999' in date_str
                if not is_standalone:
                    all_articles.append(article_data)
                    if len(date_str) >= 7 and date_str[4] == '-':
                        year = date_str[0:4]
                        month = date_str[5:7]
                        yearly_articles[year].append(article_data)
                        monthly_articles[f"{year}/{month}"].append(article_data)
                
                compiled_posts.append({
                    "type": "post",
                    "meta": meta,
                    "body_html": body_html,
                    "src_file": src_file,
                    "output_dir": output_dir,
                    "depth_prefix": depth_prefix,
                    "filename": filename,
                    "web_path": web_path,
                    "is_standalone": is_standalone
                })
            
            # ドットファイル以外の画像資産などを検出した場合、その場で出力先フォルダへ複製
            elif not filename.startswith('.'):
                os.makedirs(output_dir, exist_ok=True)
                dst_file = os.path.join(output_dir, filename)
                shutil.copy2(src_file, dst_file)
                print(f"Asset Copied: {dst_file}")

    # 2. 存在する「月別アーカイブ」のリンクHTML（<li>）を降順で組み立てる
    monthly_menu_base_list = []
    for y_m in sorted(monthly_articles.keys(), reverse=True):
        display_name = f"{y_m.replace('/', '年')}月"
        monthly_menu_base_list.append({"path": f"{y_m}/index.html", "name": display_name})

    # 3. 解析済みのデータを元に、各個別HTMLを実際のファイルに出力
    for post in compiled_posts:
        dp = post["depth_prefix"]
        m_menu_html = ""
        for m_item in monthly_menu_base_list:
            m_menu_html += f"<li><a href='{dp}{m_item['path']}'>{m_item['name']}</a></li>"
            
        if post["type"] == "profile":
            html_content = template
            html_content = html_content.replace('{{RELATIVE_DEPTH}}', '')
            html_content = html_content.replace('{{DYNAMIC_MONTHLY_MENU}}', m_menu_html)
            html_content = html_content.replace('{{TITLE}}', post["meta"].get('title', 'プロフィール'))
            html_content = html_content.replace('{{META_INFO}}', '')
            html_content = html_content.replace('{{BODY}}', post["body_html"])
            with open('profile.html', 'w', encoding='utf-8') as f:
                f.write(html_content)
            print("Compiled: profile.html")
            
        elif post["type"] == "post":
            os.makedirs(post["output_dir"], exist_ok=True)
            if post["is_standalone"]:
                meta_info_html = ''
            else:
                meta_info_html = f'<time class="post-date-meta">日付: {post["meta"].get("date", "")} | カテゴリ: {post["meta"].get("categories", "")} | タグ: {post["meta"].get("tags", "")}</time>'
            
            html_content = template
            html_content = html_content.replace('{{RELATIVE_DEPTH}}', dp)
            html_content = html_content.replace('{{DYNAMIC_MONTHLY_MENU}}', m_menu_html)
            html_content = html_content.replace('{{TITLE}}', post["meta"].get('title', ''))
            html_content = html_content.replace('{{META_INFO}}', meta_info_html)
            html_content = html_content.replace('{{BODY}}', post["body_html"])
            
            output_html_path = os.path.join(post["output_dir"], post["filename"].replace('.md', '.html'))
            with open(output_html_path, 'w', encoding='utf-8') as f:
                f.write(html_content)
            print(f"Compiled: {post['web_path']}")

    # 4. 全体検索用JSONデータの書き出し
    os.makedirs('assets', exist_ok=True)
    with open('assets/articles.json', 'w', encoding='utf-8') as f:
        json.dump(all_articles, f, ensure_ascii=False)
        
    # 5. 各種インデックスページの出力処理
    top_m_menu_html = "".join([f"<li><a href='{m['path']}'>{m['name']}</a></li>" for m in monthly_menu_base_list])
    generate_index_page(top_page_title, all_articles, '', template, 'index.html', top_m_menu_html, custom_body_html=top_page_html)
    print("Generated: index.html")
    
    for year, articles in yearly_articles.items():
        os.makedirs(year, exist_ok=True)
        target_path = os.path.join(year, 'index.html')
        year_m_menu_html = "".join([f"<li><a href='../{m['path']}'>{m['name']}</a></li>" for m in monthly_menu_base_list])
        generate_index_page(f"{year}年 記事一覧", articles, '../', template, target_path, year_m_menu_html)
        print(f"Generated: {target_path}")
        
    for y_m, articles in monthly_articles.items():
        os.makedirs(y_m, exist_ok=True)
        target_path = os.path.join(y_m, 'index.html')
        month_m_menu_html = "".join([f"<li><a href='../../{m['path']}'>{m['name']}</a></li>" for m in monthly_menu_base_list])
        generate_index_page(f"{y_m.replace('/', '年')}月 記事一覧", articles, '../../', template, target_path, month_m_menu_html)
        print(f"Generated: {target_path}")

if __name__ == '__main__':
    main()