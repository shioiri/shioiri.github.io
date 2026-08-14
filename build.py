import os
import re
import json
import shutil
import markdown
from collections import defaultdict

# SNSクローラー用のベース絶対URL定義
BASE_URL = "https://shioiri.github.io/"

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

    body_html = body.strip()
    
    # 【SNS連携：1枚目の画像ファイル名抽出回路】
    first_img_match = re.search(r'!\[.*?\]\((.*?)\)', body_html)
    if first_img_match:
        img_filename = os.path.basename(first_img_match.group(1).strip())
        meta['og_image_filename'] = img_filename
    else:
        meta['og_image_filename'] = None

    # 【SNS連携：冒頭40文字の要約抽出回路】
    plain_text = re.sub(r'!\[.*?\]\(.*?\)', '', body_html)
    plain_text = re.sub(r'<[^>]*>', '', plain_text)
    plain_text = re.sub(r'[#\*_\-`\[\]\(\)]', '', plain_text)
    plain_text = "".join(plain_text.split())
    meta['og_description'] = plain_text[:40] + ('...' if len(plain_text) > 40 else '') if plain_text else "記事の個別ページです。"

    # 【画像・キャプション配置の最適化関数（余白動的制御版）】
    def replace_image_with_caption(match):
        alt_text = match.group(1).strip()
        img_src = match.group(2).strip()
        
        # キャプションの有無による外枠マージンの動的切替（なし: 上下4px / あり: 上下15px）
        container_margin = "15px auto" if alt_text else "4px auto"
        
        html = (
            f'<div style="display:block; text-align:center; margin:{container_margin}; width:100%; max-width:400px;">'
            f'<a href="{img_src}" target="_blank" title="クリックで拡大（別タブ）" style="display:block; text-decoration:none;">'
            f'<img src="{img_src}" alt="{alt_text}" style="max-width:100%; max-height:400px; width:auto; height:auto; '
            f'display:block; margin:0 auto; border:1px solid #ddd; box-shadow:0 2px 4px rgba(0,0,0,0.05); cursor:pointer;">'
            f'</a>'
        )
        
        if alt_text:
            html += (
                f'<span style="display:block; text-align:center; font-size:0.85em; color:#666; '
                f'margin:6px auto 0 auto; padding:0; font-family:sans-serif; line-height:1.2;">'
                f'{alt_text}'
                f'</span>'
            )
            
        html += '</div>'
        return html

    body_html = re.sub(r'!\[(.*?)\]\((.*?)\)', replace_image_with_caption, body_html)
    
    # マークダウン変換
    body_html = markdown.markdown(body_html, extensions=['tables', 'nl2br'])
    
    # ── 【根本治療：画像ブロック周辺の不要タグ・余白を完全駆除】 ──
    # 1. <p> に包まれた画像ブロックを純粋な div に解放
    body_html = re.sub(r'<p>\s*(<div style="display:block; text-align:center;.*?</div>)\s*</p>', r'\1', body_html, flags=re.DOTALL)
    
    # 2. 画像ブロック同士の間に挟まった <br /> や改行を完全に消去して直結
    body_html = re.sub(
        r'(</div>)\s*(?:<br\s*/?>|\n|\r\n)+\s*(<div style="display:block; text-align:center;)',
        r'\1\2',
        body_html
    )
    
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
    index_html_content = index_html_content.replace('{{MIDDLE_META}}', '')
    index_html_content = index_html_content.replace('{{CAPTION}}', '')
    index_html_content = index_html_content.replace('{{OG_DESCRIPTION}}', '記事の一覧・アーカイブページです。')
    index_html_content = index_html_content.replace('{{OG_IMAGE_TAG}}', '')
    index_html_content = index_html_content.replace('{{TWITTER_IMAGE_TAG}}', '')
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
            html_content = html_content.replace('{{CAPTION}}', '')
            html_content = html_content.replace('{{BODY}}', post["body_html"])
            html_content = html_content.replace('{{OG_DESCRIPTION}}', '塩入友広のプロフィールページです。')
            html_content = html_content.replace('{{OG_IMAGE_TAG}}', '')
            html_content = html_content.replace('{{TWITTER_IMAGE_TAG}}', '')
            with open('profile.html', 'w', encoding='utf-8') as f:
                f.write(html_content)
            print("Compiled: profile.html")
            
        elif post["type"] == "post":
            os.makedirs(post["output_dir"], exist_ok=True)
            if post["is_standalone"]:
                meta_info_html = ''
            else:
                raw_tags = post["meta"].get("tags", "").strip()
                if raw_tags:
                    tag_list = [f"#{t.strip()}" for t in re.split(r'[, ]+', raw_tags) if t.strip()]
                    formatted_tags = " ".join(tag_list)
                else:
                    formatted_tags = ""

                p_date = post["meta"].get("date", "").strip()
                p_cat = post["meta"].get("categories", "").strip()
                
                meta_segments = [p_date, p_cat, formatted_tags]
                meta_text = " | ".join([seg for seg in meta_segments if seg])

                meta_info_html = f'<time class="post-date-meta" style="display:block; margin-bottom:35px; color:#666; font-size:0.9em;">{meta_text}</time>'
            
            # 【絶対パス同期回路】
            img_filename = post["meta"].get("og_image_filename")
            if img_filename:
                clean_dir = post["output_dir"].replace('\\', '/')
                if clean_dir == ".":
                    absolute_img_url = f"{BASE_URL}{img_filename}"
                else:
                    absolute_img_url = f"{BASE_URL}{clean_dir}/{img_filename}"
                    
                og_image_tag = f'<meta property="og:image" content="{absolute_img_url}">'
                twitter_image_tag = f'<meta name="twitter:image" content="{absolute_img_url}">'
            else:
                og_image_tag = ''
                twitter_image_tag = ''

            # 【キャプション自動判定置換回路】
            caption_text = post["meta"].get("caption", "").strip()
            if caption_text:
                caption_html = f'<div class="lead-caption">{caption_text}</div><hr class="caption-divider">'
            else:
                caption_html = ''

            # 【二重リンク限定解除回路】
            post["body_html"] = re.sub(
                r'(<a\s+href="(?!#[^"]+")[^"]+">[^<]*<div[^>]*>)\s*<a\s+href="[^"]+"\s+target="_blank"\s+title="[^"]*"\s+style="[^"]*">(<img\s+[^>]+>)</a>',
                r'\1\2',
                post["body_html"]
            )
            
            # 個別記事（post）のHTML置換回路
            html_content = template
            html_content = html_content.replace('{{RELATIVE_DEPTH}}', dp)
            html_content = html_content.replace('{{DYNAMIC_MONTHLY_MENU}}', m_menu_html)
            html_content = html_content.replace('{{TITLE}}', post["meta"].get('title', ''))
            html_content = html_content.replace('{{META_INFO}}', meta_info_html)
            html_content = html_content.replace('{{CAPTION}}', caption_html)
            html_content = html_content.replace('{{OG_DESCRIPTION}}', post["meta"].get("og_description", ""))
            html_content = html_content.replace('{{OG_IMAGE_TAG}}', og_image_tag)
            html_content = html_content.replace('{{TWITTER_IMAGE_TAG}}', twitter_image_tag)
            html_content = html_content.replace('{{BODY}}', post["body_html"])
            
            output_html_path = os.path.join(post["output_dir"], post["filename"].replace('.md', '.html'))
            with open(output_html_path, 'w', encoding='utf-8') as f:
                f.write(html_content)
            print(f"Compiled: {post['web_path']}")

    # 全体検索用JSONデータの書き出し
    os.makedirs('assets', exist_ok=True)
    with open('assets/articles.json', 'w', encoding='utf-8') as f:
        json.dump(all_articles, f, ensure_ascii=False)
        
    # 各種インデックスページの出力処理
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