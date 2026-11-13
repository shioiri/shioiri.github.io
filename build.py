#!/usr/bin/env python3
"""
塩入友広 アーカイブサイト 生成プログラム（静的サイトジェネレーター）

content/ にある原稿（Markdown または HTML）と画像から、公開用の HTML を docs/ に生成する。

使い方:
    python3 build.py            本番用の設定（config.yaml の production）で生成
    python3 build.py <環境名>    config.yaml の environments に追加した別の環境の設定で生成

処理の流れ（main() の中で、上から順に実行される）:
    1. 設定を読み込む                     load_config()
    2. 原稿を読み込み、HTML に変換する      load_posts(), load_pages()
    3. 出力先 docs/ を空の状態から作り直す   prepare_output_dir()
    4. 各ページを書き出す                   write_*()
    5. ランディングページを書き出す         write_landing_pages()

    原稿の確認は 2. の段階（load_*）ですべて済ませ、
    誤りがあれば docs/ に触れる前に停止する。

設計方針:
    - 原稿が規則に反している場合は、その場でエラーメッセージを表示して停止する。
      不完全なサイトを公開しないため、try/except による回復処理は行わない。
    - 見た目（大きさ・余白・色）はすべて static/assets/css/style.css が担う。
      このプログラムは HTML の「構造」だけを出力する。
    - docs/ は毎回すべて削除してから作り直す。原稿を削除すれば、
      対応するページも公開物から確実に消える。
"""

import argparse
import calendar
import datetime
import hashlib
import html
import json
import re
import shutil
import sys
import urllib.parse
from pathlib import Path
from xml.etree import ElementTree as etree

import markdown
import yaml
from jinja2 import Environment, FileSystemLoader, StrictUndefined, select_autoescape
from markdown.treeprocessors import Treeprocessor


# ---------------------------------------------------------------------------
# 定数：フォルダの配置と命名規則
# ---------------------------------------------------------------------------

ROOT_DIR = Path(__file__).resolve().parent
CONFIG_FILE = ROOT_DIR / 'config.yaml'
POSTS_DIR = ROOT_DIR / 'content' / 'posts'
PAGES_DIR = ROOT_DIR / 'content' / 'pages'
LANDING_DIR = ROOT_DIR / 'content' / 'landing'
TEMPLATES_DIR = ROOT_DIR / 'templates'
STATIC_DIR = ROOT_DIR / 'static'
# 複数のページで共通して使う画像の置き場所（static/assets/images/）。
# 原稿で「assets/images/○○.jpg」と書いた画像は、記事のフォルダではなくここから探す。
SHARED_IMAGE_PREFIX = 'assets/images/'
OUTPUT_DIR = ROOT_DIR / 'docs'
STYLESHEET_FILE = STATIC_DIR / 'assets' / 'css' / 'style.css'
SEARCH_SCRIPT_FILE = STATIC_DIR / 'assets' / 'js' / 'search.js'
SEARCH_INDEX_NAME = 'search.json'

# 原稿ファイルの名前（記事フォルダ・固定ページフォルダの中に、どちらか1つを置く）
#   index.md    Markdown で書いた原稿。HTML に変換して表示する
#   index.html  HTML で書いた原稿（WordPress から移した記事など）。変換せずにそのまま表示する
MARKDOWN_SOURCE = 'index.md'
HTML_SOURCE = 'index.html'
SOURCE_FILE_NAMES = (MARKDOWN_SOURCE, HTML_SOURCE)

# 旧サイトから転載したコメント（記事・固定ページのフォルダに置く。任意）
COMMENTS_FILE = 'comments.yaml'
COMMENT_KEYS = {'id', 'parent', 'author', 'date', 'timezone', 'body'}

# カテゴリとタグ：Front Matter の項目名 →（ページ名の接頭辞, 画面での呼び名）
# 画面では、カテゴリを「テーマ」、タグを「話題」と呼ぶ（一覧ページの名前は「テーマ別一覧」「話題別一覧」）。
# 各カテゴリ・タグの記事一覧は「category-上田市.html」のような名前のページになる
TAXONOMIES = {
    'categories': ('category', 'テーマ'),
    'tags': ('tag', '話題'),
}

# 一覧ページと「記事を探す」ページで、見出しの下に添える説明（初めての閲覧者向け）
TAXONOMY_NOTES = {
    'categories': '記事を内容の分野ごとに分けた一覧です。',
    'tags': '記事に出てくる地名や事柄ごとの一覧です。1つの記事に、複数の話題が付くことがあります。',
}

# カテゴリ名・タグ名に使えない文字（ファイル名や URL として扱えないもの）
TERM_FORBIDDEN = re.compile(r'[/\\?#%:*"<>|]')

# 「続きを読む」の区切り。本文の段落の間に、この1行を書く（WordPress と同じ書き方）
MORE_MARKER = '<!--more-->'

# 区切りが段落の途中にないことを確かめるために、開始と終了の数を比べるタグ
BLOCK_TAGS_TO_BALANCE = ['p', 'div', 'ul', 'ol', 'li', 'blockquote', 'table', 'figure']

# 時刻（例 18:30）と、UTC との時差（例 +09:00 / -05:00）の書式
TIME_PATTERN = re.compile(r'\d{2}:\d{2}')
TIMEZONE_PATTERN = re.compile(r'[+-]\d{2}:\d{2}')

# コメントの表示名が空の場合に表示する名前
ANONYMOUS = '匿名'

# タイトルが空の記事（旧サイトには題名のない投稿がある）を一覧等に表示するときの名前
UNTITLED = '（無題）'

# 記事ID：公開日8桁（YYYYMMDD）＋その日の連番2桁。例 2026081401
POST_ID_PATTERN = re.compile(r'\d{10}')

# 固定ページ名：英小文字・数字・ハイフン。例 profile
PAGE_NAME_PATTERN = re.compile(r'[a-z][a-z0-9-]*')

# Front Matter に書いてよい項目
POST_KEYS = {'title', 'caption', 'date', 'time', 'timezone', 'updated', 'categories', 'tags', 'hidden', 'image'}
PAGE_KEYS = {'title', 'caption', 'order', 'hidden', 'menu', 'head_title', 'description', 'image'}
LANDING_KEYS = {'title', 'caption', 'hidden'}

# ランディングページのフォルダ名：英小文字・数字・ハイフン。例 26（公開 URL は …/26/）
LANDING_NAME_PATTERN = re.compile(r'[a-z0-9][a-z0-9-]*')

# Front Matter：ファイル先頭の「---」で囲まれた部分（YAML形式）
FRONT_MATTER_PATTERN = re.compile(r'\A---\n(.*?)\n---\n(.*)\Z', re.DOTALL)


# ---------------------------------------------------------------------------
# 共通の小さな関数
# ---------------------------------------------------------------------------

def fail(message):
    """エラーメッセージを表示してプログラムを終了する（終了コード 1）。"""
    sys.exit(f'エラー: {message}')


def rel(path):
    """エラーメッセージ用に、パスをこのプログラムのフォルダからの相対表記にする。"""
    return path.relative_to(ROOT_DIR)


def is_blank(text):
    """文字列が None または空白だけなら True。"""
    return text is None or text.strip() == ''


def to_list(value):
    """Front Matter の値を文字列のリストにそろえる。
    「tags: 議会」のような単独の値も、「tags: [上田市, 議会]」のようなリストも受け付ける。"""
    if value is None:
        return []
    if isinstance(value, list):
        return [str(item) for item in value]
    return [str(value)]


def absolute_url(base_url, path):
    """サイト内のパスを、http から始まる完全な URL にする（外部 URL はそのまま）。"""
    if '://' in path:
        return path
    return base_url + path


def is_local_path(url):
    """原稿フォルダ内のファイルを指す相対パスなら True。
    「https:」「mailto:」のような接頭辞や、「/」「#」で始まるものは対象外。"""
    return url != '' and ':' not in url and not url.startswith(('/', '#'))


# ---------------------------------------------------------------------------
# 1. 設定の読み込み
# ---------------------------------------------------------------------------

def load_config(environment_name):
    """config.yaml を読み込み、サイト共通の設定と、指定した環境の設定を返す。"""
    config = yaml.safe_load(CONFIG_FILE.read_text(encoding='utf-8'))
    environments = config['environments']
    if environment_name not in environments:
        fail(f'config.yaml に環境 "{environment_name}" がありません。'
             f'指定できるのは {", ".join(environments)} です')
    return config['site'], environments[environment_name]


# ---------------------------------------------------------------------------
# 2. 原稿の読み込みと変換
# ---------------------------------------------------------------------------

def read_source(path, allowed_keys):
    """原稿ファイルを読み、Front Matter（辞書）と本文（Markdown文字列）に分ける。"""
    text = path.read_text(encoding='utf-8').replace('\r\n', '\n')
    match = FRONT_MATTER_PATTERN.match(text)
    if match is None:
        fail(f'{rel(path)}: 先頭に Front Matter（--- で囲んだ設定部分）がありません')

    meta = yaml.safe_load(match.group(1)) or {}
    unknown_keys = set(meta) - allowed_keys
    if unknown_keys:
        fail(f'{rel(path)}: 使えない項目があります: {", ".join(sorted(unknown_keys))}'
             f'（使える項目: {", ".join(sorted(allowed_keys))}）')
    # title は必須の項目。ただし旧サイトの題名のない投稿のため、空欄（""）は認める
    if 'title' not in meta:
        fail(f'{rel(path)}: title がありません')
    if meta['title'] is None:
        meta['title'] = ''
    return meta, match.group(2)


def load_comments(folder):
    """comments.yaml を読み込み、返信関係を入れ子にしたリストで返す。ファイルがなければ空のリスト。

    comments.yaml の形式（1件ごと）:
        - id: 123            コメントの番号（旧サイトでの番号）
          parent: 120        返信先のコメントの番号（返信でなければ省略）
          author: 名前       表示名
          date: 2007-07-12 18:30
          body: |-           本文（テキスト。HTML は使えない）
            ...
    戻り値では、各コメントの 'children' に、そのコメントへの返信が入る。"""
    path = folder / COMMENTS_FILE
    if not path.is_file():
        return []
    comments = yaml.safe_load(path.read_text(encoding='utf-8')) or []
    by_id = {}
    for comment in comments:
        unknown_keys = set(comment) - COMMENT_KEYS
        if unknown_keys:
            fail(f'{rel(path)}: 使えない項目があります: {", ".join(sorted(unknown_keys))}')
        comment['children'] = []
        comment['author'] = comment.get('author') or ANONYMOUS
        by_id[comment['id']] = comment

    top_level = []
    for comment in comments:
        parent_id = comment.get('parent')
        if parent_id is None:
            top_level.append(comment)
        elif parent_id in by_id:
            by_id[parent_id]['children'].append(comment)
        else:
            fail(f'{rel(path)}: コメント {comment["id"]} の返信先 {parent_id} がありません')
    return top_level


class FigureProcessor(Treeprocessor):
    """Markdown を HTML に変換する途中の「要素ツリー」を書き換える処理。

    Python-Markdown を拡張するときの標準的な作法として、Treeprocessor を継承し、
    run() に処理を書く。このプログラムでクラスを使うのはここだけで、
    実際の処理は下の2つの関数に任せている。

      rewrite_local_paths()      画像・リンクのパスを公開時の位置に書き換える
      convert_image_paragraphs() 画像だけの段落を <figure> に変換する
    """

    def __init__(self, md, source_dir, media_prefix, site_root):
        super().__init__(md)
        self.source_dir = source_dir
        self.media_prefix = media_prefix
        self.site_root = site_root
        self.first_image = None  # 本文の最初の画像（SNS 用の og:image に使う）

    def run(self, root):
        rewrite_local_paths(root, self.source_dir, self.media_prefix, self.site_root)
        convert_image_paragraphs(root)
        first_image = root.find('.//img')
        if first_image is not None:
            self.first_image = first_image.get('src')


def rewrite_local_paths(root, source_dir, media_prefix, site_root):
    """原稿フォルダ内のファイルを指すパスを、公開時の位置に書き換える。
    例: 08a.png → 2026081401/08a.png
    「assets/images/」で始まる画像は共通の画像（static/assets/images/）として扱い、
    サイトの最上位からの位置にする（site_root はこのページから見た最上位："" か "../"）。
    画像ファイルが存在しない場合はエラーで停止する。"""
    for image in root.iter('img'):
        src = image.get('src', '')
        if src.startswith(SHARED_IMAGE_PREFIX):
            if not (STATIC_DIR / src).is_file():
                fail(f'{rel(source_dir)}: 共通の画像ファイル "{src}" が static/ にありません')
            image.set('src', f'{site_root}{src}')
        elif is_local_path(src):
            if not (source_dir / src).is_file():
                fail(f'{rel(source_dir)}: 画像ファイル "{src}" がありません')
            image.set('src', f'{media_prefix}/{src}')
        # 画像は画面に近づいたときに読み込む（画像の多い記事でも、ページの表示を待たせないため）
        image.set('loading', 'lazy')

    # リンク先は、原稿フォルダ内に実在するファイル（PDF など）だけを書き換える。
    # 「2026081401.html」のような他ページへのリンクはそのまま残す。
    for link in root.iter('a'):
        href = link.get('href', '')
        if is_local_path(href) and (source_dir / href).is_file():
            link.set('href', f'{media_prefix}/{href}')


def rewrite_local_paths_in_html(body, source_dir, media_prefix):
    """HTML で書いた原稿の src・href のうち、原稿フォルダ内に実在するファイルを指すものを、
    公開時の位置に書き換える（例: src="01.jpg" → src="2026021501/01.jpg"）。
    HTML の本文は変換せずに使うため、Markdown の原稿とは別にここで書き換える。"""
    def replace(match):
        value = match.group(3)
        if is_local_path(value) and (source_dir / value).is_file():
            value = f'{media_prefix}/{value}'
        return f'{match.group(1)}{match.group(2)}{value}{match.group(2)}'
    return re.sub(r'(\b(?:src|href)=)(["\'])([^"\']*)\2', replace, body)


def convert_image_paragraphs(root):
    """画像だけで構成された段落（<p>）を、画像1枚ごとの <figure> に置き換える。
    文章の中に混ざった画像は対象外とし、そのまま残す。"""
    for parent in list(root.iter()):
        new_children = []
        for child in parent:
            if child.tag == 'p' and is_image_only(child):
                for item in child:
                    if item.tag != 'br':
                        new_children.append(make_figure(item))
            else:
                new_children.append(child)
        parent[:] = new_children


def is_image_only(paragraph):
    """段落の中身が「画像」「リンク付き画像」「改行」だけなら True。

    改行（<br>）を許すのは、空行を挟まずに画像を続けて書いた場合
    （nl2br 拡張により、1つの段落の中で <br> で区切られる）に対応するため。"""
    if not is_blank(paragraph.text) or paragraph.find('.//img') is None:
        return False
    for item in paragraph:
        if not is_blank(item.tail):
            return False
        is_image = item.tag == 'img'
        is_linked_image = (item.tag == 'a' and is_blank(item.text)
                           and len(item) == 1 and item[0].tag == 'img'
                           and is_blank(item[0].tail))
        if not (item.tag == 'br' or is_image or is_linked_image):
            return False
    return True


def make_figure(item):
    """画像（<img>）またはリンク付き画像（<a><img></a>）から、次の構造を作る。

        <figure class="...">
          <a href="リンク先" target="_blank"><img src="..." alt="説明"></a>
          <figcaption>説明</figcaption>      ← 説明（alt）がある場合のみ
        </figure>

    リンク先は、リンク付き画像ならそのリンク先、単独の画像なら画像自身（拡大表示）。
    画像に付けたクラス（例: {: .wide }）は <figure> に移す。"""
    if item.tag == 'a':
        image = item[0]
        link = item.get('href')
    else:
        image = item
        link = image.get('src')

    caption = image.get('alt', '').strip()
    classes = image.get('class', '').split()
    if caption == '':
        classes.append('no-caption')
    image.attrib.pop('class', None)
    image.tail = None

    figure = etree.Element('figure')
    if classes:
        figure.set('class', ' '.join(classes))
    anchor = etree.SubElement(figure, 'a', {'href': link, 'target': '_blank'})
    anchor.append(image)
    if caption:
        figcaption = etree.SubElement(figure, 'figcaption')
        figcaption.text = caption
    figure.tail = '\n'
    return figure


def markdown_to_html(text, source_dir, media_prefix, site_root):
    """Markdown を HTML に変換する。戻り値は（HTML, 最初の画像のパス）。

    使用する拡張機能（いずれも Python-Markdown に標準で含まれる）:
      tables     表
      nl2br      原稿中の1回の改行を、そのまま改行（<br>）として表示する
      attr_list  {: .wide } のように要素へクラスを付ける"""
    md = markdown.Markdown(extensions=['tables', 'nl2br', 'attr_list'])
    processor = FigureProcessor(md, source_dir, media_prefix, site_root)
    # 優先度 5：attr_list（優先度 8）がクラスを付けた後に実行させるため
    md.treeprocessors.register(processor, 'figure', 5)
    body_html = md.convert(text)
    return body_html, processor.first_image


def find_source(folder):
    """原稿フォルダの中の原稿ファイル（index.md か index.html）を返す。
    どちらもない場合、または両方ある場合はエラーで停止する。"""
    found = [folder / name for name in SOURCE_FILE_NAMES if (folder / name).is_file()]
    if len(found) != 1:
        fail(f'{rel(folder)}: 原稿ファイル（{" か ".join(SOURCE_FILE_NAMES)}）を1つだけ置いてください')
    return found[0]


def convert_body(source_path, body, media_prefix, site_root):
    """原稿の本文を表示用の HTML にする。戻り値は（HTML, 最初の画像のパス）。
    index.md なら Markdown として変換し、index.html なら本文をそのまま使う。
    media_prefix: 原稿フォルダ内の画像の公開時の置き場所、site_root: このページから見たサイトの最上位"""
    if source_path.name == MARKDOWN_SOURCE:
        return markdown_to_html(body, source_path.parent, media_prefix, site_root)
    body = rewrite_local_paths_in_html(body, source_path.parent, media_prefix)
    first_image = re.search(r'<img\b[^>]*\bsrc=["\']([^"\']+)', body)
    return body, first_image.group(1) if first_image else None


def split_more(body_html, source_path):
    """本文を「続きを読む」の区切りで分ける。戻り値は（トップページ用の抜粋, 記事ページ用の全文）。

    区切りがない記事は、抜粋を None とし、全文はそのまま返す。
    区切りがある記事は、区切りの前までを抜粋とし、全文では区切りの位置に目印（id="more"）を置く。
    「続きを読む」のリンクは、この目印（記事ページの #more）を指す。"""
    count = body_html.count(MORE_MARKER)
    if count == 0:
        return None, body_html
    if count > 1:
        fail(f'{rel(source_path)}: 「{MORE_MARKER}」は1つの記事に1か所だけ書けます')

    excerpt = body_html.split(MORE_MARKER)[0]
    # 段落などの途中で切ると HTML の構造が壊れるため、開始タグと終了タグの数が合うことを確かめる
    for tag in BLOCK_TAGS_TO_BALANCE:
        opened = len(re.findall(rf'<{tag}[\s>]', excerpt, flags=re.IGNORECASE))
        closed = len(re.findall(rf'</{tag}>', excerpt, flags=re.IGNORECASE))
        if opened != closed:
            fail(f'{rel(source_path)}: 「{MORE_MARKER}」は段落の途中ではなく、段落の間に1行で書いてください')
    return excerpt.strip() + '\n', body_html.replace(MORE_MARKER, '<div id="more"></div>')


def term_page(prefix, name):
    """カテゴリ・タグの一覧ページについて、（ファイル名, リンク用のパス）を返す。
    日本語の名前はリンクでは %E4%B8%8A... のように符号化する（URL の決まりによる）。"""
    file_name = f'{prefix}-{name}.html'
    return file_name, urllib.parse.quote(file_name)


def term_links(prefix, names):
    """カテゴリ名・タグ名のリストを、テンプレートで使う {'name', 'path'} のリストにする。"""
    return [{'name': name, 'path': term_page(prefix, name)[1]} for name in names]


def plain_text(body_html):
    """HTML からタグを除き、文字参照（&amp; 等）を戻し、連続する空白を1つにしたテキストを返す。"""
    text = re.sub(r'<[^>]+>', ' ', body_html)
    return ' '.join(html.unescape(text).split())


def make_description(caption, body_html, length):
    """SNS 用の説明文を作る。キャプションがあればそれを使い、
    なければ本文（画像部分を除く）の先頭から指定文字数を取り出す。"""
    if caption:
        return caption
    text = re.sub(r'<figure.*?</figure>', '', body_html, flags=re.DOTALL)
    text = re.sub(r'<[^>]+>', '', text)
    text = ''.join(html.unescape(text).split())
    if len(text) > length:
        return text[:length] + '…'
    return text


def copy_media(source_dir, media_prefix):
    """原稿フォルダ内の原稿ファイル以外のファイルを、docs/<media_prefix>/ に複製する。"""
    for path in sorted(source_dir.iterdir()):
        if path.name in SOURCE_FILE_NAMES or path.name == COMMENTS_FILE or path.name.startswith('.'):
            continue
        if path.is_dir():
            fail(f'{rel(path)}: 原稿フォルダの中にフォルダは置けません')
        destination = OUTPUT_DIR / media_prefix / path.name
        destination.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(path, destination)


def is_hidden(meta, source_path):
    """Front Matter に hidden: true がある原稿なら True（サイトに表示しない）。
    hidden: true の原稿は、記事ページ・一覧・検索・フィード等のどこにも出力しない。
    ただし原稿のファイルはリポジトリに残るため、リポジトリが公開されていれば誰でも読める点に注意。"""
    hidden = meta.get('hidden', False)
    if not isinstance(hidden, bool):
        fail(f'{rel(source_path)}: hidden には true か false を書いてください')
    return hidden


def load_posts(site, env):
    """content/posts/ の記事をすべて読み込む。
    戻り値は（表示する記事の新しい順のリスト, 非表示にした記事のパスの集合）。"""
    posts = []
    hidden_paths = set()
    for folder in sorted(POSTS_DIR.iterdir(), reverse=True):
        if folder.name.startswith('.'):
            continue
        post = load_post(folder, site, env)
        if post is None:
            hidden_paths.add(f'{folder.name}.html')
        else:
            posts.append(post)
    return posts, hidden_paths


def image_path(meta, source_path, media_prefix):
    """原稿の image（SNS 用の画像）に書いた画像の、サイトの最上位から見たパスを返す。
    「assets/images/」で始まる場合は共通の画像（static/assets/images/）、それ以外は原稿と同じフォルダの画像。
    ファイルがない場合はエラーで停止する。"""
    if meta['image'].startswith(SHARED_IMAGE_PREFIX):
        if not (STATIC_DIR / meta['image']).is_file():
            fail(f'{rel(source_path)}: image に書いた共通の画像 {meta["image"]} が static/ にありません')
        return meta['image']
    if not (source_path.parent / meta['image']).is_file():
        fail(f'{rel(source_path)}: image に書いたファイル {meta["image"]} がフォルダにありません')
    return f'{media_prefix}/{meta["image"]}'


def load_post(folder, site, env):
    """記事フォルダ1件を読み込み、テンプレートに渡す情報を辞書にまとめる。非表示の記事なら None を返す。"""
    post_id = folder.name
    if POST_ID_PATTERN.fullmatch(post_id) is None:
        fail(f'{rel(folder)}: 記事フォルダの名前は10桁の数字（例 2026081401）にしてください')
    year, month, day = int(post_id[0:4]), int(post_id[4:6]), int(post_id[6:8])
    if not (1 <= month <= 12 and 1 <= day <= calendar.monthrange(year, month)[1]):
        fail(f'{rel(folder)}: フォルダ名の日付 {post_id[:8]} は存在しない日付です')
    published = datetime.date(year, month, day)

    source_path = find_source(folder)
    meta, body = read_source(source_path, POST_KEYS)
    if is_hidden(meta, source_path):
        return None

    # 公開日の根拠はフォルダ名（記事ID）だけ。date を書いた場合は一致を確認する。
    if 'date' in meta and str(meta['date']) != published.isoformat():
        fail(f'{rel(folder)}: date（{meta["date"]}）がフォルダ名の日付'
             f'（{published.isoformat()}）と一致しません')
    # 時刻は任意（旧サイトから移した記事にだけある）。時差を省略した場合はサイトの既定値とする
    time = str(meta.get('time', ''))
    timezone = str(meta.get('timezone', site['timezone']))
    if time and TIME_PATTERN.fullmatch(time) is None:
        fail(f'{rel(folder)}: time は「18:30」の形で書いてください')
    if TIMEZONE_PATTERN.fullmatch(timezone) is None:
        fail(f'{rel(folder)}: timezone は「+09:00」の形で書いてください')

    updated = None
    if 'updated' in meta:
        updated = datetime.date.fromisoformat(str(meta['updated']))

    body_html, first_image = convert_body(source_path, body, post_id, '')
    excerpt_html, body_html = split_more(body_html, source_path)
    caption = meta.get('caption', '')
    # image を書いた場合は、その画像を SNS 用の画像にし、記事のタイトルの上にも表示する。
    # 書かない場合は、本文の最初の画像を SNS 用の画像にする（タイトルの上には表示しない）
    header_image = None
    if 'image' in meta:
        header_image = image_path(meta, source_path, post_id)
        first_image = header_image
    return {
        'id': post_id,
        'source_dir': folder,
        'source_name': source_path.name,
        'path': f'{post_id}.html',
        'published': published,
        'time': time,
        'timezone': timezone,
        'updated': updated,
        'title': meta['title'] or UNTITLED,
        'caption': caption,
        'categories': term_links('category', to_list(meta.get('categories'))),
        'tags': term_links('tag', to_list(meta.get('tags'))),
        'body_html': body_html,
        'excerpt_html': excerpt_html,
        'header_image': header_image,
        'comments': load_comments(folder),
        'description': make_description(caption, body_html, site['description_length']),
        'og_image': absolute_url(env['base_url'], first_image) if first_image else None,
    }


def load_pages(site, env):
    """content/pages/ の固定ページをすべて読み込む。フォルダ名 top はトップページ（index.html）の
    上部に表示する内容として扱う。戻り値は（名前をキーにした辞書, 非表示にしたページのパスの集合）。"""
    pages = {}
    hidden_paths = set()
    for folder in sorted(PAGES_DIR.iterdir()):
        if folder.name.startswith('.'):
            continue
        name = folder.name
        if PAGE_NAME_PATTERN.fullmatch(name) is None:
            fail(f'{rel(folder)}: 固定ページのフォルダ名は英小文字で始めてください（例 profile）')

        source_path = find_source(folder)
        meta, body = read_source(source_path, PAGE_KEYS)
        if is_hidden(meta, source_path):
            if name == 'top':
                fail(f'{rel(source_path)}: トップページは非表示にできません')
            hidden_paths.add(f'{name}.html')
            continue
        order = meta.get('order', 0)
        if not isinstance(order, int):
            fail(f'{rel(source_path)}: order には整数を書いてください')
        menu = meta.get('menu', True)
        if not isinstance(menu, bool):
            fail(f'{rel(source_path)}: menu には true か false を書いてください')
        body_html, first_image = convert_body(source_path, body, name, '')
        caption = meta.get('caption', '')
        # 検索結果・SNS 用の説明文：description を書いた場合はそれを使い、なければ自動で作る
        description = meta.get('description') or make_description(caption, body_html, site['description_length'])
        # SNS 用の画像：image に書いた場合はその画像、なければ本文の最初の画像
        if 'image' in meta:
            first_image = image_path(meta, source_path, name)
        pages[name] = {
            'name': name,
            'source_dir': folder,
            'source_name': source_path.name,
            'order': order,
            'menu': menu,
            'path': '' if name == 'top' else f'{name}.html',
            'title': meta['title'] or UNTITLED,
            'head_title': meta.get('head_title', ''),
            'caption': caption,
            'body_html': body_html,
            'comments': load_comments(folder),
            'description': description,
            'og_image': absolute_url(env['base_url'], first_image) if first_image else None,
        }
    if 'top' not in pages:
        fail(f'{rel(PAGES_DIR / "top")} がありません')
    return pages, hidden_paths


def load_landing_pages(site, env):
    """content/landing/ のランディングページをすべて読み込み、リストで返す（フォルダがなければ空）。

    ランディングページは、選挙期間中の案内など、サイトの外から直接案内するためのページ。
    公開 URL は「サイトの URL/フォルダ名/」（例 …/26/）で、サイドメニューを持たない。
    記事・固定ページとは別扱いで、メニュー・一覧・検索・フィード・sitemap には載せない。"""
    if not LANDING_DIR.is_dir():
        return []
    landing_pages = []
    for folder in sorted(LANDING_DIR.iterdir()):
        if folder.name.startswith('.'):
            continue
        name = folder.name
        if LANDING_NAME_PATTERN.fullmatch(name) is None:
            fail(f'{rel(folder)}: ランディングページのフォルダ名は英小文字・数字・ハイフンにしてください（例 26）')
        source_path = find_source(folder)
        meta, body = read_source(source_path, LANDING_KEYS)
        if is_hidden(meta, source_path):
            continue
        # 画像等はページと同じフォルダ（docs/<名前>/）に置くので、パスは「./画像」になる。
        # ページはサイトの最上位から1階層下にあるので、共通の画像は「../assets/images/○○」になる
        body_html, first_image = convert_body(source_path, body, '.', '../')
        # SNS 用の画像の完全な URL：共通の画像（../ で始まる）はサイトの最上位から、それ以外はページの位置から
        if first_image and first_image.startswith('../'):
            og_image = absolute_url(env['base_url'], first_image[len('../'):])
        elif first_image:
            og_image = absolute_url(env['base_url'] + f'{name}/', first_image)
        else:
            og_image = None
        caption = meta.get('caption', '')
        landing_pages.append({
            'name': name,
            'source_dir': folder,
            'path': f'{name}/',
            'title': meta['title'],
            'caption': caption,
            'body_html': body_html,
            'description': make_description(caption, body_html, site['description_length']),
            'og_image': og_image,
        })
    return landing_pages


def group_posts(posts, key_length):
    """記事を ID の先頭 key_length 桁でまとめる（4桁なら年別、6桁なら月別）。
    戻り値は {'2026': [記事, ...], ...}。記事の並び順（新しい順）は保たれる。"""
    groups = {}
    for post in posts:
        groups.setdefault(post['id'][:key_length], []).append(post)
    return groups


def group_by_term(posts, key):
    """記事をカテゴリ別（key='categories'）またはタグ別（key='tags'）にまとめる。
    戻り値は {'名前': [記事, ...]}。記事の並び順（新しい順）は保たれる。"""
    groups = {}
    for post in posts:
        for term in post[key]:
            groups.setdefault(term['name'], []).append(post)
    return groups


def find_links_to_hidden(posts, pages, hidden_paths):
    """表示する記事・固定ページの本文から、非表示にしたページへのリンクを探す。
    戻り値は [(リンク元のパス, リンク先のパス), ...]。リンク切れになるため、ビルドの最後に一覧を表示する。"""
    found = []
    for item in posts + list(pages.values()):
        for target in re.findall(r'href=["\']([^"\'#?]+)', item['body_html']):
            if target in hidden_paths:
                found.append((item['path'] or 'index.html', target))
    return found


def check_terms(posts):
    """カテゴリ名・タグ名がページの名前として使えるかを確認する。
    Mac・Windows のファイルシステムは英字の大文字と小文字を区別しないため、
    大文字・小文字だけが違う名前（例 Test と test）もエラーとする。"""
    for key, (prefix, label) in TAXONOMIES.items():
        seen = {}
        for name in group_by_term(posts, key):
            if TERM_FORBIDDEN.search(name) or name.startswith('.') or name.strip() != name or name == '':
                fail(f'{label}「{name}」に使えない文字が含まれています')
            if name.lower() in seen:
                fail(f'{label}「{name}」と「{seen[name.lower()]}」は大文字・小文字だけが違うため、両立できません')
            seen[name.lower()] = name


def make_nav_years(posts):
    """メニューに表示する年・月の一覧を作る（新しい順）。
    戻り値の例: [{'key': '2026', 'label': '2026年', 'path': '2026.html',
                   'months': [{'path': '202609.html', 'label': '09月'}, ...]}, ...]"""
    nav_years = []
    for year_key, year_posts in group_posts(posts, 4).items():
        months = [{'path': f'{month_key}.html', 'label': f'{month_key[4:6]}月'}
                  for month_key in group_posts(year_posts, 6)]
        nav_years.append({'key': year_key, 'label': year_label(year_key),
                          'path': f'{year_key}.html', 'months': months})
    return nav_years


def make_nav_pages(pages):
    """メニューに表示する固定ページの一覧を作る。order の小さい順、同じなら名前順。
    top はトップページそのものなので含めない。Front Matter に menu: false と書いたページも含めない
    （ページ自体は公開し、他のページからのリンクでたどれるようにする場合に使う）。"""
    menu_pages = [page for page in pages.values() if page['name'] != 'top' and page['menu']]
    menu_pages.sort(key=lambda page: (page['order'], page['name']))
    return [{'path': page['path'], 'title': page['title']} for page in menu_pages]


def year_label(key):
    return f'{key}年'


def month_label(key):
    return f'{key[:4]}年{key[4:6]}月'


# ---------------------------------------------------------------------------
# 3. 出力先の準備
# ---------------------------------------------------------------------------

def prepare_output_dir():
    """docs/ を削除し、static/ の内容（CSS など）を複製して作り直す。"""
    if OUTPUT_DIR.exists():
        shutil.rmtree(OUTPUT_DIR)
    shutil.copytree(STATIC_DIR, OUTPUT_DIR)
    # GitHub Pages に Jekyll での加工をさせず、docs/ をそのまま公開させるための目印
    (OUTPUT_DIR / '.nojekyll').write_text('', encoding='utf-8')


# ---------------------------------------------------------------------------
# 4. ページの書き出し
# ---------------------------------------------------------------------------

def file_version(path):
    """ファイルの内容から、短い識別子（SHA-256 ハッシュの先頭8文字）を作る。

    HTML から CSS を「style.css?v=識別子」の形で参照するために使う（キャッシュバスティング）。
    ブラウザは一度取得した CSS を一定時間保存して使い回すため、CSS を更新しても
    古い見た目のまま表示されることがある。内容が変われば識別子が変わり、
    参照先の URL も変わるので、ブラウザは必ず新しい CSS を取得する。"""
    return hashlib.sha256(path.read_bytes()).hexdigest()[:8]


def make_template_environment():
    """テンプレートエンジン（Jinja2）を準備する。
    autoescape：タイトル等に含まれる & < > " を自動でエスケープし、HTML の崩れを防ぐ。
    StrictUndefined：テンプレート内の変数名の誤りを、空欄にせずエラーとして検出する。"""
    return Environment(
        loader=FileSystemLoader(TEMPLATES_DIR),
        autoescape=select_autoescape(['html', 'xml']),
        undefined=StrictUndefined,
        trim_blocks=True,
        lstrip_blocks=True,
        keep_trailing_newline=True,
    )


def render(templates, common, template_name, output_name, **context):
    """テンプレートに値を差し込み、docs/<output_name> に書き出す。"""
    output_path = OUTPUT_DIR / output_name
    if output_path.exists():
        fail(f'{output_name} が重複して生成されようとしました')
    html_text = templates.get_template(template_name).render(**common, **context)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(html_text, encoding='utf-8')


def write_posts(templates, common, posts):
    """個別記事のページを書き出す。
    posts は新しい順に並んでいるので、1つ前の要素が「次の記事」（より新しい記事）、
    1つ後の要素が「前の記事」（より古い記事）になる。端の記事では None とする。"""
    for index, post in enumerate(posts):
        newer = posts[index - 1] if index > 0 else None
        older = posts[index + 1] if index + 1 < len(posts) else None
        render(templates, common, 'post.html', post['path'],
               current_year=post['id'][:4],
               title=post['title'], path=post['path'],
               description=post['description'], og_image=post['og_image'],
               og_type='article', post=post, newer=newer, older=older)
        copy_media(post['source_dir'], post['id'])


def write_pages(templates, common, pages, posts):
    """固定ページを書き出す。top だけは、最新の記事（一覧と全文）と組み合わせて index.html にする。"""
    latest_year = posts[0]['id'][:4] if posts else ''
    for page in pages.values():
        if page['name'] == 'top':
            render(templates, common, 'home.html', 'index.html',
                   current_year=latest_year,
                   title=page['title'], path=page['path'],
                   description=page['description'], og_image=page['og_image'],
                   og_type='website', head_title=page['head_title'], intro_html=page['body_html'],
                   list_posts=posts[:common['site']['top_list_entries']],
                   posts=posts[:common['site']['top_entries']])
        else:
            render(templates, common, 'page.html', page['path'],
                   current_year='',
                   title=page['title'], path=page['path'],
                   description=page['description'], og_image=page['og_image'],
                   og_type='website', head_title=page['head_title'], page=page)
        copy_media(page['source_dir'], page['name'])


def month_page_path(key, number):
    """月別一覧の各ページのパス。1ページめは 202608.html、2ページめ以降は 202608-2.html の形。"""
    return f'{key}.html' if number == 1 else f'{key}-{number}.html'


def write_archives(templates, common, posts):
    """年別一覧（2026.html）と月別一覧（202608.html）を書き出す。
    年別はタイトルと日付の一覧、月別は本文を archive_entries 件ずつ複数のページに分けて表示する。"""
    for key, group in group_posts(posts, 4).items():
        title = f'{year_label(key)} 記事一覧'
        path = f'{key}.html'
        render(templates, common, 'list.html', path,
               current_year=key, title=title, path=path, description=title, og_image=None,
               og_type='website', posts=group)

    size = common['site']['archive_entries']
    for key, group in group_posts(posts, 6).items():
        chunks = [group[start:start + size] for start in range(0, len(group), size)]
        page_links = [{'number': number, 'path': month_page_path(key, number)}
                      for number in range(1, len(chunks) + 1)]
        for number, chunk in enumerate(chunks, start=1):
            title = f'{month_label(key)} の記事'
            if number > 1:
                title += f'（{number}/{len(chunks)}）'
            path = month_page_path(key, number)
            render(templates, common, 'month.html', path,
                   current_year=key[:4], title=title, path=path, description=title, og_image=None,
                   og_type='website', posts=chunk, page_links=page_links, page_number=number,
                   newer_page=month_page_path(key, number - 1) if number > 1 else None,
                   older_page=month_page_path(key, number + 1) if number < len(chunks) else None)


def write_search_index(posts, pages):
    """サイト内検索用のデータ（search.json）を書き出し、その内容から作った識別子を返す。

    形式: [[パス, タイトル, 日付, 本文のテキスト], ...]（記事は新しい順、固定ページは日付が空）
    検索は閲覧者のブラウザが行う（static/assets/js/search.js）。識別子は、
    データが変わったときにブラウザが保存済みの古いデータを使わないよう、URL に付ける。"""
    entries = [[post['path'], post['title'], post['published'].isoformat(), plain_text(post['body_html'])]
               for post in posts]
    entries += [[page['path'] or 'index.html', page['title'], '', plain_text(page['body_html'])]
                for page in pages.values() if page['name'] != 'top']
    data = json.dumps(entries, ensure_ascii=False, separators=(',', ':'))
    (OUTPUT_DIR / SEARCH_INDEX_NAME).write_text(data, encoding='utf-8')
    return hashlib.sha256(data.encode('utf-8')).hexdigest()[:8]


def write_search_page(templates, common, posts, index_version):
    """「記事を探す」ページ（search.html）を書き出す。
    言葉での検索欄の下に、テーマ別・年月別・話題別の一覧を並べ、記事の探し方を1ページにまとめる。"""
    render(templates, common, 'search.html', 'search.html',
           current_year='', title='記事を探す', path='search.html',
           description='言葉での検索、テーマ別・年月別・話題別の一覧から、記事を探せます',
           og_image=None, og_type='website',
           themes=make_terms(posts, 'categories'), topics=make_terms(posts, 'tags'),
           notes=TAXONOMY_NOTES,
           index_url=f'{SEARCH_INDEX_NAME}?v={index_version}',
           script_url=f'assets/js/search.js?v={file_version(SEARCH_SCRIPT_FILE)}')


def make_terms(posts, key):
    """カテゴリ（key='categories'）またはタグ（key='tags'）の一覧を作る。記事数の多い順、同じなら名前順。
    戻り値の例: [{'name': '上田市', 'path': 'tag-上田市.html', 'count': 46}, ...]"""
    prefix = TAXONOMIES[key][0]
    groups = group_by_term(posts, key)
    return [{'name': name, 'path': term_page(prefix, name)[1], 'count': len(group)}
            for name, group in sorted(groups.items(), key=lambda item: (-len(item[1]), item[0]))]


def write_taxonomies(templates, common, posts):
    """カテゴリ・タグの一覧（categories.html、tags.html）と、
    各カテゴリ・タグの記事一覧（category-○○.html、tag-○○.html）を書き出す。
    書き出したページのパス（sitemap.xml 用）を返す。"""
    paths = []
    for key, (prefix, label) in TAXONOMIES.items():
        groups = group_by_term(posts, key)
        terms = make_terms(posts, key)
        index_path = f'{key}.html'
        title = f'{label}別一覧'
        render(templates, common, 'terms.html', index_path,
               current_year='', title=title, path=index_path,
               description=title, og_image=None, og_type='website', terms=terms, note=TAXONOMY_NOTES[key])
        paths.append(index_path)

        for term in terms:
            file_name = term_page(prefix, term['name'])[0]
            title = f'{label}「{term["name"]}」の記事一覧'
            render(templates, common, 'list.html', file_name,
                   current_year='', title=title, path=term['path'],
                   description=title, og_image=None, og_type='website', posts=groups[term['name']])
            paths.append(term['path'])
    return paths


def write_feed_and_sitemap(templates, common, posts, pages, extra_paths):
    """Atom フィード（feed.xml）、sitemap.xml、robots.txt を書き出す。"""
    feed_posts = posts[:common['site']['feed_entries']]
    feed_updated = max((post['updated'] or post['published']) for post in feed_posts)
    render(templates, common, 'feed.xml', 'feed.xml',
           posts=feed_posts, feed_updated=feed_updated)

    archive_paths = [f'{key}.html' for key in group_posts(posts, 4)]
    archive_paths += [f'{key}.html' for key in group_posts(posts, 6)]
    archive_paths += extra_paths
    render(templates, common, 'sitemap.xml', 'sitemap.xml',
           posts=posts, pages=list(pages.values()), archive_paths=archive_paths)
    render(templates, common, 'robots.txt', 'robots.txt')


# ---------------------------------------------------------------------------
# 5. ランディングページ
# ---------------------------------------------------------------------------

def write_landing_pages(templates, common, landing_pages):
    """ランディングページを docs/<名前>/index.html に書き出す。
    他のページと名前が重なる場合（例 assets は CSS 等のフォルダと重なる）はエラーで停止する。
    そのため、他のすべてのページを書き出した後に呼ぶ。"""
    for page in landing_pages:
        if (OUTPUT_DIR / page['name']).exists():
            fail(f'{rel(page["source_dir"])}: 「{page["name"]}」は他のページと重なるため使えません')
        render(templates, common, 'landing.html', f'{page["name"]}/index.html',
               title=page['title'], path=page['path'], description=page['description'],
               og_image=page['og_image'], og_type='website', page=page)
        copy_media(page['source_dir'], page['name'])


# ---------------------------------------------------------------------------
# 全体の流れ
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(description='content/ の原稿から docs/ に公開用サイトを生成する')
    parser.add_argument('environment', nargs='?', default='production',
                        help='config.yaml の environments の名前（省略時 production）')
    args = parser.parse_args()

    # 1. 設定
    site, env = load_config(args.environment)

    # 2. 原稿の読み込み（ここで原稿の誤りが見つかれば、docs/ に触れる前に停止する）
    posts, hidden_post_paths = load_posts(site, env)
    pages, hidden_page_paths = load_pages(site, env)
    landing_pages = load_landing_pages(site, env)
    hidden_paths = hidden_post_paths | hidden_page_paths
    links_to_hidden = find_links_to_hidden(posts, pages, hidden_paths)
    check_terms(posts)

    # 3. 出力先の準備
    prepare_output_dir()

    # 4. 書き出し。common は全ページのテンプレートに共通で渡す値
    templates = make_template_environment()
    common = {'site': site, 'env': env,
              'nav_years': make_nav_years(posts), 'nav_pages': make_nav_pages(pages),
              'css_version': file_version(STYLESHEET_FILE)}
    write_posts(templates, common, posts)
    write_pages(templates, common, pages, posts)
    write_archives(templates, common, posts)
    taxonomy_paths = write_taxonomies(templates, common, posts)
    write_feed_and_sitemap(templates, common, posts, pages, taxonomy_paths)
    write_search_page(templates, common, posts, write_search_index(posts, pages))

    # 5. ランディングページ（他のページとの重なりを確かめるため、最後に書き出す）
    write_landing_pages(templates, common, landing_pages)

    print(f'完了: 記事 {len(posts)} 件、固定ページ {len(pages)} 件を '
          f'{OUTPUT_DIR.relative_to(ROOT_DIR)}/ に出力しました（環境: {args.environment}）')
    if hidden_paths:
        print(f'参考: 非表示（hidden: true）の原稿が {len(hidden_paths)} 件あります（サイトには出力していません）')
    if links_to_hidden:
        print(f'注意: 非表示のページへのリンクが {len(links_to_hidden)} 件あります（リンク切れになります）')
        for source, target in links_to_hidden:
            print(f'  {source} → {target}')
    html_sources = sum(1 for item in posts + list(pages.values()) if item['source_name'] == HTML_SOURCE)
    if html_sources:
        print(f'参考: HTML 形式の原稿（index.html）が {html_sources} 件あります（Markdown 化の残り）')


if __name__ == '__main__':
    main()
