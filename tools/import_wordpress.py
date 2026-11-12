#!/usr/bin/env python3
"""
WordPress のエクスポートファイル（WXR 形式の XML）から、記事と固定ページを content/ に取り込む。
旧サイト（smile.shioiri.jp）からの移行時に、一度だけ使うプログラム。

使い方（リポジトリの最上位のフォルダで実行する）:
    python3 tools/import_wordpress.py <エクスポートファイル.xml>

出力:
    content/posts/<記事ID>/index.html      本文（WordPress が表示していた形の HTML）
    content/posts/<記事ID>/comments.yaml   承認済みコメント（ある場合のみ）
    content/pages/<名前>/index.html        固定ページ（PAGES に指定したもの）
    画面への報告                            件数、書き換えられなかったリンクなど

取り込まない情報（エクスポートファイルにだけ残す）:
    コメント投稿者のメールアドレス・IPアドレス・URL、未承認コメント、スパム、ピンバック、
    下書き・非公開・ごみ箱の投稿

処理の流れ:
    1. エクスポートファイルを読み込む（規格外の文字を補正する）     read_export()
    2. 公開済みの記事に記事ID（公開日8桁＋連番2桁）を割り当てる    assign_post_ids()
    3. 旧サイトの URL と新しいパスの対応表を作る                    make_link_map()
    4. 本文を変換し、ファイルに書き出す                            convert_content(), write_item()
"""

import collections
import datetime
import html
import re
import sys
from pathlib import Path
from xml.etree import ElementTree as etree

import yaml


# ---------------------------------------------------------------------------
# 定数
# ---------------------------------------------------------------------------

ROOT_DIR = Path(__file__).resolve().parent.parent
POSTS_DIR = ROOT_DIR / 'content' / 'posts'
PAGES_DIR = ROOT_DIR / 'content' / 'pages'

# WXR ファイルで使われている XML 名前空間
NAMESPACES = {
    'wp': 'http://wordpress.org/export/1.2/',
    'content': 'http://purl.org/rss/1.0/modules/content/',
}

# 取り込む固定ページ：旧サイトでのスラッグ（URL の番号）→（新しいフォルダ名, メニューの並び順）
# プライバシーポリシーは新サイトの実態に合わせて書き直すため、取り込まない。
PAGES = {
    '0432613741': ('about', 2),
    '6717655512': ('recommend', 3),
    '4962664752': ('guestbook', 4),
}

# 分類として意味を持たないカテゴリ（取り込まない）
IGNORED_CATEGORIES = {'未分類', 'Uncategorized'}

# 本文から取り除く要素：外部のスクリプト（第三者のコードがこのサイト上で動くのを防ぐ）と、
# 現在のブラウザでは表示できない Flash 等のプラグイン
REMOVED_ELEMENTS = ['script', 'object', 'embed']

# WordPress の wpautop() がブロック要素として扱うタグ（wp-includes/formatting.php の $allblocks）
BLOCK_TAGS = ('table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre'
              '|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|legend|section'
              '|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary')

# 旧サイト内へのリンク（href 属性の値）
OLD_SITE_LINK = re.compile(r'https?://smile\.shioiri\.jp/(?:(\d{10})/?|\?(?:p|page_id)=(\d+))(#[^"\']*)?')


def utc_offset(local, gmt):
    """現地時刻と協定世界時（UTC）の差を「+09:00」「-05:00」の形で返す。
    WordPress は投稿日時を、投稿時のサイトの時刻（post_date）と UTC（post_date_gmt）の両方で記録している。"""
    difference = datetime.datetime.fromisoformat(local) - datetime.datetime.fromisoformat(gmt)
    minutes = int(difference.total_seconds() // 60)
    sign = '+' if minutes >= 0 else '-'
    return f'{sign}{abs(minutes) // 60:02d}:{abs(minutes) % 60:02d}'


def fail(message):
    """エラーメッセージを表示してプログラムを終了する（終了コード 1）。"""
    sys.exit(f'エラー: {message}')


def text_of(item, path):
    """XML 要素の中の、指定した子要素の文字列を返す（なければ空文字列）。"""
    return item.findtext(path, default='', namespaces=NAMESPACES)


# ---------------------------------------------------------------------------
# 1. エクスポートファイルの読み込み
# ---------------------------------------------------------------------------

def decode_iso2022jp(match):
    """ISO-2022-JP（旧来の携帯メール等の文字コード）で埋め込まれた部分を、通常の文字に戻す。
    UTF-8 として読んだ時点で各バイトが1文字ずつになっているため、latin1 でバイト列に戻してから解釈する。"""
    return match.group(0).encode('latin1').decode('iso2022_jp')


def read_export(path):
    """エクスポートファイルを読み込み、<item> 要素（投稿・固定ページ等）のリストを返す。

    このファイルには XML の規格で使えない文字が含まれているため、解析の前に補正する。
      - ISO-2022-JP の文字列（ESC $ B ... ESC ( B）→ 通常の文字に変換する
      - 制御文字（タブ・改行以外）→ 取り除く"""
    text = path.read_text(encoding='utf-8')
    text = re.sub(r'\x1b\$[B@].*?\x1b\([BJ]', decode_iso2022jp, text, flags=re.DOTALL)
    text = re.sub(r'[\x00-\x08\x0b\x0c\x0e-\x1f]', '', text)
    channel = etree.fromstring(text).find('channel')
    return channel.findall('item')


def published_items(items, post_type):
    """指定した種類（post / page）の、公開済みのものだけを返す。"""
    return [item for item in items
            if text_of(item, 'wp:post_type') == post_type
            and text_of(item, 'wp:status') == 'publish']


# ---------------------------------------------------------------------------
# 2. 記事IDの割り当て
# ---------------------------------------------------------------------------

def assign_post_ids(posts):
    """記事を公開日時の順に並べ、記事ID（YYYYMMDD＋その日の連番2桁）を割り当てる。
    戻り値は [(記事ID, item), ...]。同じ日時の記事は、WordPress の投稿番号の順にする。"""
    posts = sorted(posts, key=lambda item: (text_of(item, 'wp:post_date'),
                                            int(text_of(item, 'wp:post_id'))))
    counter = collections.Counter()
    result = []
    for item in posts:
        day = text_of(item, 'wp:post_date')[:10].replace('-', '')
        counter[day] += 1
        if counter[day] > 99:
            fail(f'{day} の記事が 99 件を超えたため、記事IDを割り当てられません')
        result.append((f'{day}{counter[day]:02d}', item))
    return result


# ---------------------------------------------------------------------------
# 3. 旧サイトの URL と新しいパスの対応表
# ---------------------------------------------------------------------------

def make_link_map(posts_with_ids, pages):
    """旧サイトでの記事の指し方（URL の10桁の番号、または投稿番号）から、新しいパスを引く辞書を作る。
    例: {'1789845097': '1969011501.html', '4496': '2005042701.html', ...}"""
    link_map = {}
    for post_id, item in posts_with_ids:
        link_map[text_of(item, 'wp:post_name')] = f'{post_id}.html'
        link_map[text_of(item, 'wp:post_id')] = f'{post_id}.html'
    for item in pages:
        name, _ = PAGES[text_of(item, 'wp:post_name')]
        link_map[text_of(item, 'wp:post_name')] = f'{name}.html'
        link_map[text_of(item, 'wp:post_id')] = f'{name}.html'
    return link_map


def rewrite_links(body, link_map, report):
    """本文中の、旧サイト内の記事へのリンクを、新しいパスに書き換える。
    対応する記事がないリンクは書き換えずに残し、報告に記録する。"""
    def replace(match):
        key = match.group(1) or match.group(2)
        if key not in link_map:
            report['unresolved_links'].append(match.group(0))
            return match.group(0)
        report['rewritten_links'] += 1
        return link_map[key] + (match.group(3) or '')

    return re.sub(r'(?<=href=["\'])' + OLD_SITE_LINK.pattern, replace, body)


# ---------------------------------------------------------------------------
# 4. 本文の変換
# ---------------------------------------------------------------------------

def wpautop(text):
    """WordPress の wpautop() 関数を簡略化して移植したもの。

    WordPress は本文を段落タグなしで保存し、表示のたびにこの関数で
    「空行 → 段落（<p>）」「改行 → <br>」に変換している。
    移行後は表示時の変換を行わないため、取り込みの時点で1回だけ変換しておく。"""
    if text.strip() == '':
        return ''
    text = text.replace('\r\n', '\n').replace('\r', '\n') + '\n'

    # <pre> の中の改行は変換しないため、目印に置き換えて退避しておく
    pre_blocks = []

    def stash_pre(match):
        pre_blocks.append(match.group(0))
        return f'<pre wp-pre-tag-{len(pre_blocks) - 1}></pre>'

    text = re.sub(r'<pre[\s>].*?</pre>', stash_pre, text, flags=re.DOTALL | re.IGNORECASE)

    # 連続した <br> は段落の区切りとみなす
    text = re.sub(r'<br\s*/?>\s*<br\s*/?>', '\n\n', text)

    # ブロック要素の前後に空行を入れ、段落の区切りにする
    text = re.sub(rf'(<(?:{BLOCK_TAGS})\b)', r'\n\n\1', text, flags=re.IGNORECASE)
    text = re.sub(rf'(</(?:{BLOCK_TAGS})>)', r'\1\n\n', text, flags=re.IGNORECASE)

    # 空行で区切られたかたまりを <p> で囲む
    paragraphs = re.split(r'\n\s*\n', text)
    text = ''.join(f'<p>{paragraph.strip()}</p>\n' for paragraph in paragraphs if paragraph.strip())

    # ブロック要素を囲んでしまった <p> を外す
    text = re.sub(rf'<p>\s*(</?(?:{BLOCK_TAGS})\b[^>]*>)', r'\1', text, flags=re.IGNORECASE)
    text = re.sub(rf'(</?(?:{BLOCK_TAGS})\b[^>]*>)\s*</p>', r'\1', text, flags=re.IGNORECASE)
    text = re.sub(r'<p>\s*</p>', '', text)

    # 残った改行を <br> にする。タグの内部（属性の途中）の改行は空白に置き換えて対象外にする
    text = re.sub(r'<[^<>]*>', lambda match: match.group(0).replace('\n', ' '), text)
    text = re.sub(r'(?<!<br />)\s*\n', '<br />\n', text)
    text = re.sub(rf'(</?(?:{BLOCK_TAGS})\b[^>]*>)\s*<br />', r'\1', text, flags=re.IGNORECASE)
    text = re.sub(r'<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)\b[^>]*>)', r'\1',
                  text, flags=re.IGNORECASE)

    # 退避しておいた <pre> を戻す
    for index, block in enumerate(pre_blocks):
        text = text.replace(f'<pre wp-pre-tag-{index}></pre>', block)
    return text.strip() + '\n'


def convert_content(content, link_map, report):
    """WordPress の本文を、表示用の HTML に変換する。"""
    # ブロックエディタ（WordPress 5.0 以降）で書かれた本文には wpautop() が適用されない
    is_block_editor = '<!-- wp:' in content

    # HTML コメント（ブロックエディタの区切り、<!--more-->、コメントアウトされた古い埋め込み）を除く
    content = re.sub(r'<!--.*?-->', '', content, flags=re.DOTALL)

    for tag in REMOVED_ELEMENTS:
        pattern = rf'<{tag}\b.*?</{tag}>|<{tag}\b[^>]*>'
        found = len(re.findall(pattern, content, flags=re.DOTALL | re.IGNORECASE))
        if found:
            report['removed_elements'][tag] += found
            content = re.sub(pattern, '', content, flags=re.DOTALL | re.IGNORECASE)

    # 埋め込み（地図・動画）の http:// を https:// にする。
    # https のページから http の埋め込みを読み込むことはブラウザが禁止しているため
    content = re.sub(r'(<iframe\b[^>]*\bsrc=["\'])http://', r'\1https://', content, flags=re.IGNORECASE)

    if not is_block_editor:
        content = wpautop(content)
    return rewrite_links(content.strip() + '\n', link_map, report)


def comment_to_text(content):
    """コメントの本文を、HTML を含まないテキストにする。
    第三者が書いた HTML をそのまま表示しないため、タグを除き、文字参照（&amp; 等）を戻す。"""
    content = re.sub(r'<br\s*/?>', '\n', content, flags=re.IGNORECASE)
    content = re.sub(r'<[^>]+>', '', content)
    return html.unescape(content).replace('\r\n', '\n').strip()


def extract_comments(item, report):
    """承認済みの通常コメントを、comments.yaml に書く形（辞書のリスト）にして返す。
    取り込む項目は、番号・返信先・表示名・日時・本文だけ。"""
    approved = [comment for comment in item.findall('wp:comment', NAMESPACES)
                if text_of(comment, 'wp:comment_approved') == '1'
                and text_of(comment, 'wp:comment_type') in ('', 'comment')]
    approved_ids = {int(text_of(comment, 'wp:comment_id')) for comment in approved}

    comments = []
    for comment in approved:
        local = text_of(comment, 'wp:comment_date')
        record = {
            'id': int(text_of(comment, 'wp:comment_id')),
            'author': html.unescape(text_of(comment, 'wp:comment_author')),
            'date': local[:16],
            'timezone': utc_offset(local, text_of(comment, 'wp:comment_date_gmt')),
            'body': comment_to_text(text_of(comment, 'wp:comment_content')),
        }
        parent = int(text_of(comment, 'wp:comment_parent') or 0)
        if parent in approved_ids:
            record['parent'] = parent
        elif parent != 0:
            # 返信先が未承認・削除済みの場合は、返信ではない通常のコメントとして扱う
            report['orphan_comments'] += 1
        comments.append(record)
    comments.sort(key=lambda record: (record['date'], record['id']))
    report['comments'] += len(comments)
    return comments


def categories_and_tags(item):
    """記事のカテゴリとタグを取り出す（意味を持たないカテゴリは除く）。"""
    categories = [html.unescape(element.text) for element in item.findall('category')
                  if element.get('domain') == 'category' and element.text not in IGNORED_CATEGORIES]
    tags = [html.unescape(element.text) for element in item.findall('category')
            if element.get('domain') == 'post_tag']
    return categories, tags


def represent_text(dumper, value):
    """YAML に書き出すとき、複数行の文字列は「|」形式（改行をそのまま書く形）にする。人が読みやすくするため。"""
    if '\n' in value:
        style = '|'
    elif ':' in value:
        style = "'"  # 「-05:00」等が 60 進数の数値と解釈されないよう、引用符で囲む
    else:
        style = None
    return dumper.represent_scalar('tag:yaml.org,2002:str', value, style=style)


yaml.SafeDumper.add_representer(str, represent_text)


def write_item(folder, front_matter, body, comments):
    """原稿フォルダを作り、index.html（と comments.yaml）を書き出す。"""
    if folder.exists():
        fail(f'{folder} はすでに存在します。取り込みは空の状態から行ってください')
    folder.mkdir(parents=True)
    header = yaml.safe_dump(front_matter, allow_unicode=True, sort_keys=False, width=1000)
    (folder / 'index.html').write_text(f'---\n{header}---\n{body}', encoding='utf-8')
    if comments:
        (folder / 'comments.yaml').write_text(
            yaml.safe_dump(comments, allow_unicode=True, sort_keys=False, width=1000),
            encoding='utf-8')


# ---------------------------------------------------------------------------
# 全体の流れ
# ---------------------------------------------------------------------------

def main():
    if len(sys.argv) != 2:
        fail('使い方: python3 tools/import_wordpress.py <エクスポートファイル.xml>')

    # 1. 読み込み
    items = read_export(Path(sys.argv[1]))
    posts = published_items(items, 'post')
    pages = [item for item in published_items(items, 'page')
             if text_of(item, 'wp:post_name') in PAGES]

    # 2.〜3. 記事IDの割り当てと、リンクの対応表
    posts_with_ids = assign_post_ids(posts)
    link_map = make_link_map(posts_with_ids, pages)

    # 4. 変換と書き出し
    report = {'comments': 0, 'orphan_comments': 0, 'untitled': 0, 'no_time': 0, 'rewritten_links': 0,
              'unresolved_links': [], 'removed_elements': collections.Counter()}

    for post_id, item in posts_with_ids:
        title = html.unescape(text_of(item, 'title')).strip()
        if title == '':
            report['untitled'] += 1
        categories, tags = categories_and_tags(item)
        front_matter = {'title': title}
        # 時刻が 00:00:00 ちょうどの記事は、日付だけで記録された投稿（他のサービスから
        # WordPress に移した古い記事など）とみなし、時刻を付けない
        local = text_of(item, 'wp:post_date')
        if not local.endswith('00:00:00'):
            front_matter['time'] = local[11:16]
            front_matter['timezone'] = utc_offset(local, text_of(item, 'wp:post_date_gmt'))
        else:
            report['no_time'] += 1
        if categories:
            front_matter['categories'] = categories
        if tags:
            front_matter['tags'] = tags
        body = convert_content(text_of(item, 'content:encoded'), link_map, report)
        write_item(POSTS_DIR / post_id, front_matter, body, extract_comments(item, report))

    for item in pages:
        name, order = PAGES[text_of(item, 'wp:post_name')]
        front_matter = {'title': html.unescape(text_of(item, 'title')), 'order': order}
        body = convert_content(text_of(item, 'content:encoded'), link_map, report)
        write_item(PAGES_DIR / name, front_matter, body, extract_comments(item, report))

    # 報告
    print(f'記事 {len(posts_with_ids)} 件、固定ページ {len(pages)} 件を取り込みました')
    print(f'  コメント: {report["comments"]} 件'
          f'（うち返信先が見つからず通常のコメントとした分 {report["orphan_comments"]} 件）')
    print(f'  タイトルのない記事: {report["untitled"]} 件')
    print(f'  時刻のない記事（00:00:00）: {report["no_time"]} 件')
    print(f'  サイト内リンクの書き換え: {report["rewritten_links"]} 件')
    print(f'  取り除いた要素: {dict(report["removed_elements"])}')
    print(f'  書き換えられなかったサイト内リンク: {len(report["unresolved_links"])} 件')
    for link in report['unresolved_links']:
        print(f'    {link}')


if __name__ == '__main__':
    main()
