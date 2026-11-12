#!/usr/bin/env python3
"""
Google サイト（sites.google.com/view/shioiri）の「マッチ箱」のページを、記事として取り込む。
1パック（Pack#001 など）を1件の記事にする。移行時に1回だけ使用した。

使い方（リポジトリの最上位のフォルダで実行する）:
    python3 tools/import_matchbox.py <packs.json> <画像のフォルダ>

入力は、Google データエクスポート（Takeout）で書き出した Google サイトの HTML から作った次の2つ。
  packs.json      パックごとの公開日と、品目（番号・説明文・元の投稿の URL・画像ファイル名）の一覧
  画像のフォルダ  PackNNN/<番号>-<連番>.jpg（長辺 800px に縮小済み）

記事IDは、パックの公開日（Google サイトの更新履歴の日付）の記事のうち、まだ使われていない最小の連番を割り当てる。
説明文のハッシュタグ（#上田市 など）は「#」を外して本文に残し、市町村名のものは記事のタグにもする。
"""

import json
import re
import shutil
import sys
from pathlib import Path

ROOT_DIR = Path(__file__).resolve().parent.parent
POSTS_DIR = ROOT_DIR / 'content' / 'posts'

# 説明文から取り除くハッシュタグ（すべての品目に付いているため）
DROPPED_TAGS = {'マッチ箱広告', 'マッチラベル'}
# 記事のタグにするハッシュタグ（市町村名）
PLACE_TAG = re.compile(r'.+[市町村区]')
# Markdown で特別な意味を持つ文字（説明文中のものはそのまま表示させるためエスケープする）
MARKDOWN_SPECIALS = re.compile(r'([\\`*_\[\]<>])')


def fail(message):
    print(f'エラー: {message}', file=sys.stderr)
    sys.exit(1)


def next_post_id(day):
    """指定した日（YYYYMMDD）の記事で、まだ使われていない最小の記事IDを返す。"""
    for number in range(1, 100):
        post_id = f'{day}{number:02d}'
        if not (POSTS_DIR / post_id).exists():
            return post_id
    fail(f'{day} の記事が 99 件を超えたため、記事IDを割り当てられません')


def clean_line(line):
    """説明文の1行から、不要なハッシュタグを除き、残りのハッシュタグの「#」を外す。"""
    words = [word for word in line.split() if word.lstrip('#') not in DROPPED_TAGS]
    text = ' '.join(word[1:] if word.startswith('#') else word for word in words)
    return MARKDOWN_SPECIALS.sub(r'\\\1', text)


def place_tags(pack):
    """パック内の説明文のハッシュタグのうち、市町村名のものを、出現順に重複なく返す。"""
    tags = []
    for item in pack['items']:
        for line in item['lines']:
            for tag in re.findall(r'#(\S+)', line):
                if PLACE_TAG.fullmatch(tag) and tag not in tags:
                    tags.append(tag)
    return tags


def x_url(tweet_url):
    """元の投稿の URL を、現在の X のドメイン（x.com）の形にする。"""
    return re.sub(r'^https?://twitter\.com/', 'https://x.com/', tweet_url)


def item_markdown(item, number_to_post, post_id):
    """品目1点分の Markdown を返す。見出しには「#no0001」の形の目印を付け、他の品目からリンクできるようにする。"""
    number = item['num']
    heading = f'{number}（重複）' if item['duplicate'] else number
    parts = [f'### {heading} {{: #no{number} }}']
    if item['files']:
        parts.append('\n'.join(f'![]({name})' for name in item['files']))
    lines = [clean_line(line) for line in item['lines'] if clean_line(line)]
    if item['ref']:
        ref_post = number_to_post.get(item['ref'])
        target = f'#no{item["ref"]}' if ref_post == post_id else f'{ref_post}.html#no{item["ref"]}'
        lines.append(f'[{item["ref"]}]({target}) と同じものです。' if ref_post else f'{item["ref"]} と同じものです。')
    if lines:
        parts.append('\n'.join(lines))
    if item['tweet']:
        parts.append(f'[元の投稿（X）]({x_url(item["tweet"])})')
    return '\n\n'.join(parts)


def main():
    if len(sys.argv) != 3:
        fail('使い方: python3 tools/import_matchbox.py <packs.json> <画像のフォルダ>')
    packs = json.loads(Path(sys.argv[1]).read_text(encoding='utf-8'))
    image_dir = Path(sys.argv[2])

    # 先に全パックの記事IDを決め、品目番号から記事IDを引けるようにする（重複品のリンク先に使う）
    post_ids = {}
    for pack in packs:
        post_id = next_post_id(pack['date'].replace('-', ''))
        (POSTS_DIR / post_id).mkdir()
        post_ids[pack['pack']] = post_id
    number_to_post = {item['num']: post_ids[pack['pack']] for pack in packs for item in pack['items']
                      if not item['duplicate']}

    for pack in packs:
        post_id = post_ids[pack['pack']]
        folder = POSTS_DIR / post_id
        first, last = pack['items'][0]['num'], pack['items'][-1]['num']
        label = pack['pack'].replace('Pack', 'Pack#')
        tags = place_tags(pack)
        front = [
            '---',
            f'title: "マッチ箱 {label}（{first}〜{last}）"',
            'categories: "マッチ箱"',
            f'tags: [{", ".join(json.dumps(tag, ensure_ascii=False) for tag in tags)}]',
            '---',
        ]
        intro = (f'住居の取り壊しの際にお預かりしたマッチ箱を、写真に撮って残しています。'
                 f'このページは {label}（No.{first}〜{last}）です。\n'
                 f'活動の説明と、ほかのパックの一覧は[マッチ箱](matchbox.html)のページにあります。')
        body = [intro] + [item_markdown(item, number_to_post, post_id) for item in pack['items']]
        (folder / 'index.md').write_text('\n'.join(front) + '\n\n' + '\n\n'.join(body) + '\n', encoding='utf-8')
        for item in pack['items']:
            for name in item['files']:
                shutil.copy2(image_dir / pack['pack'] / name, folder / name)
        print(f'{pack["pack"]} → {post_id}（{len(pack["items"])} 点）')


if __name__ == '__main__':
    main()
