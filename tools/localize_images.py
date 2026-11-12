#!/usr/bin/env python3
"""
記事が参照している外部の画像（自分の画像に限る）をダウンロードして記事フォルダに置き、
本文の参照をファイル名に書き換える。

使い方（リポジトリの最上位のフォルダで実行する）:
    python3 tools/localize_images.py <記事ID> [<記事ID> ...]

- 対象は SOURCES に挙げた URL で始まる画像だけ。Amazon・Flickr など他者の画像は対象外
- 長辺が MAX_SIDE を超える画像は MAX_SIDE に縮小する（小さい画像は拡大しない）
- GIF はアニメーションを保つため、縮小せずにそのまま置く
- ファイル名は本文に現れた順に 01.jpg、02.png ……（記事フォルダに同じ名前があれば次の番号）
- 同じ URL が本文に複数回現れる場合（リンク先と表示画像など）は、1つのファイルにまとめて置き換える
- index.md・index.html のどちらにも使える
- 必要なライブラリ：Pillow（pip install Pillow）。サイトの生成（build.py）には不要
"""

import io
import re
import sys
import urllib.request
from pathlib import Path

from PIL import Image, ImageOps

ROOT_DIR = Path(__file__).resolve().parent.parent
POSTS_DIR = ROOT_DIR / 'content' / 'posts'

# 取り込む対象の URL の先頭部分（自分が置いた画像の保存先）
SOURCES = [
    'https://ext.netlify.app/',
    'https://d2fuek8fvjoyvv.cloudfront.net/shioiri.theletter.jp/',
]
MAX_SIDE = 800
JPEG_QUALITY = 85
IMAGE_URL = re.compile(r'https?://[^\s"\'()<>]+?\.(?:jpe?g|png|gif)', re.IGNORECASE)


def source_file(post_dir):
    """記事の原稿ファイル（index.md か index.html）を返す。"""
    markdown = post_dir / 'index.md'
    if markdown.exists():
        return markdown
    return post_dir / 'index.html'


def target_urls(text):
    """本文に現れる対象の画像の URL を、現れた順に重複なく返す。"""
    urls = []
    for url in IMAGE_URL.findall(text):
        if url.startswith(tuple(SOURCES)) and url not in urls:
            urls.append(url)
    return urls


def next_name(post_dir, number, extension):
    """記事フォルダで使われていないファイル名と、次に使う番号を返す。"""
    while (post_dir / f'{number:02d}{extension}').exists():
        number += 1
    return f'{number:02d}{extension}', number + 1


def save_image(data, path):
    """画像を長辺 MAX_SIDE 以下に縮小して保存する。GIF はそのまま保存する。"""
    if path.suffix == '.gif':
        path.write_bytes(data)
        return
    image = ImageOps.exif_transpose(Image.open(io.BytesIO(data)))
    image.thumbnail((MAX_SIDE, MAX_SIDE), Image.LANCZOS)
    if path.suffix == '.png':
        image.save(path, optimize=True)
    else:
        image.convert('RGB').save(path, quality=JPEG_QUALITY, optimize=True, progressive=True)


def localize(post_id):
    post_dir = POSTS_DIR / post_id
    source = source_file(post_dir)
    text = source.read_text(encoding='utf-8')
    number = 1
    for url in target_urls(text):
        extension = Path(url).suffix.lower().replace('.jpeg', '.jpg')
        name, number = next_name(post_dir, number, extension)
        with urllib.request.urlopen(url) as response:
            data = response.read()
        save_image(data, post_dir / name)
        text = text.replace(url, name)
        print(f'{post_id}/{name}  {len(data):>9,} → {(post_dir / name).stat().st_size:>9,} バイト  {url}')
    source.write_text(text, encoding='utf-8')


def main():
    for post_id in sys.argv[1:]:
        localize(post_id)


if __name__ == '__main__':
    main()
