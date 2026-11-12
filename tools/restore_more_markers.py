#!/usr/bin/env python3
"""
旧サイトから取り込んだ記事に、「続きを読む」の区切り（<!--more-->）を復元する。
取り込み時（import_wordpress.py）に HTML コメントと一緒に除去した区切りを、元の位置に戻すために一度だけ使う。

使い方（リポジトリの最上位のフォルダで実行する）:
    python3 tools/restore_more_markers.py <エクスポートファイル.xml>          確認だけ行う（ファイルは変更しない）
    python3 tools/restore_more_markers.py <エクスポートファイル.xml> --write  確認のうえ、ファイルに書き込む

安全のための方針:
    - 取り込み後に修正した記事を壊さないよう、取り込み処理を再実行せず、現在の原稿に区切りの1行を挿入するだけにする
    - 挿入位置は、エクスポートファイルの区切りの位置を、取り込み処理と同じ変換にかけて求める
    - 次の確認をすべて満たす記事だけに書き込む
        1. 区切りの前後がタグとタグの間であること（段落の途中ではないこと）
        2. 区切りの前の部分で、段落などの開始タグと終了タグの数が合うこと（build.py と同じ確認）
        3. 目印を入れて変換した結果から目印を除くと、現在の原稿と（空白の違いを除いて）一致すること
           （＝現在の原稿が取り込み時のままで、文字数による位置の対応づけが成り立つこと）
    - 書き込む内容は、現在の原稿の該当位置に「<!--more-->」の1行を加えたものに限る
"""

import collections
import re
import sys
from pathlib import Path

import import_wordpress as wp

MORE = '<!--more-->'
TOKEN = 'ZZMOREMARKERZZ'  # 位置を探すための目印。本文に現れない文字列
BALANCED_TAGS = ['p', 'div', 'ul', 'ol', 'li', 'blockquote', 'table', 'figure']


def empty_report():
    return {'comments': 0, 'orphan_comments': 0, 'untitled': 0, 'no_time': 0, 'rewritten_links': 0,
            'unresolved_links': [], 'removed_elements': collections.Counter()}


def non_space_length(text):
    """空白（改行を含む）を除いた文字数。"""
    return len(re.sub(r'\s', '', text))


def insert_position(body, count):
    """body の中で、空白以外の文字をちょうど count 文字読んだ直後の位置を返す。"""
    seen = 0
    for index, char in enumerate(body):
        if seen == count:
            return index
        if not char.isspace():
            seen += 1
    return len(body)


def is_balanced(html):
    return all(len(re.findall(rf'<{tag}[\s>]', html, flags=re.IGNORECASE))
               == len(re.findall(rf'</{tag}>', html, flags=re.IGNORECASE))
               for tag in BALANCED_TAGS)


def plan_marker(raw, body, link_map):
    """区切りを挿入した本文を返す。安全に挿入できない場合は、理由の文字列を返す。

    エクスポートの本文の区切りを、独立した段落（前後に空行）の目印に置き換えて取り込み処理と同じ変換をかけ、
    目印より前にある空白以外の文字数を数える。現在の原稿で同じ文字数を読んだ位置が、区切りを入れる位置になる。"""
    marked = wp.convert_content(re.sub(r'<!--more(.*?)-->', f'\n\n{TOKEN}\n\n', raw, count=1),
                                link_map, empty_report())
    without_token = re.sub(r'(<p>\s*)?' + TOKEN + r'(\s*</p>)?', '', marked)
    if re.sub(r'\s', '', without_token) != re.sub(r'\s', '', body):
        return '区切りを除いた結果が現在の原稿と一致しない（取り込み後に修正された可能性）'
    before = re.sub(r'(<p>\s*)?' + TOKEN + r'.*', '', marked, flags=re.DOTALL)
    position = insert_position(body, non_space_length(before))
    head, tail = body[:position].rstrip(), body[position:].lstrip()

    if not (head == '' or head.endswith('>')) or not (tail == '' or tail.startswith('<')):
        return '区切りの位置が段落の途中になる'
    if not is_balanced(head):
        return '区切りの前でタグの開始と終了の数が合わない'
    separator = '' if body[:position].endswith('\n') or position == 0 else '\n'
    return body[:position] + separator + MORE + '\n' + body[position:]


def main():
    if len(sys.argv) not in (2, 3) or (len(sys.argv) == 3 and sys.argv[2] != '--write'):
        wp.fail('使い方: python3 tools/restore_more_markers.py <エクスポートファイル.xml> [--write]')
    write = len(sys.argv) == 3

    items = wp.read_export(Path(sys.argv[1]))
    pages = [item for item in wp.published_items(items, 'page') if wp.text_of(item, 'wp:post_name') in wp.PAGES]
    posts_with_ids = wp.assign_post_ids(wp.published_items(items, 'post'))
    link_map = wp.make_link_map(posts_with_ids, pages)

    restored, skipped = [], []
    for post_id, item in posts_with_ids:
        raw = wp.text_of(item, 'content:encoded')
        if not re.search(r'<!--more(.*?)-->', raw):
            continue
        path = wp.POSTS_DIR / post_id / 'index.html'
        if not path.is_file():
            skipped.append((post_id, '原稿（index.html）がない'))
            continue
        front, body = path.read_text(encoding='utf-8').split('\n---\n', 1)
        if MORE in body:
            skipped.append((post_id, 'すでに区切りがある'))
            continue
        result = plan_marker(raw, body, link_map)
        if not result.startswith(('<', '\n')) and MORE not in result:
            skipped.append((post_id, result))
            continue
        restored.append(post_id)
        if write:
            path.write_text(front + '\n---\n' + result, encoding='utf-8')

    print(f'区切りを{"復元しました" if write else "復元できます"}: {len(restored)} 件')
    print(f'対象外: {len(skipped)} 件')
    for post_id, reason in skipped:
        print(f'  {post_id}  {reason}')


if __name__ == '__main__':
    main()
