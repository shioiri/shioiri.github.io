#!/usr/bin/env python3
"""
WordPress のエクスポートファイルから、投稿番号を指定した記事だけを取り込み、公開記事として追加する。
下書きのまま残っていた記事を個別に追加するときに使う（import_wordpress.py の処理を流用する）。

使い方（リポジトリの最上位のフォルダで実行する）:
    python3 tools/import_selected_posts.py <エクスポートファイル.xml> <投稿番号> [<投稿番号> ...]

記事IDは、公開日の記事のうち、まだ使われていない最小の連番を割り当てる。
"""

import collections
import html
import sys

import import_wordpress as wp


def next_post_id(day):
    """指定した日（YYYYMMDD）の記事で、まだ使われていない最小の記事IDを返す。"""
    for number in range(1, 100):
        post_id = f'{day}{number:02d}'
        if not (wp.POSTS_DIR / post_id).exists():
            return post_id
    wp.fail(f'{day} の記事が 99 件を超えたため、記事IDを割り当てられません')


def main():
    if len(sys.argv) < 3:
        wp.fail('使い方: python3 tools/import_selected_posts.py <エクスポートファイル.xml> <投稿番号> ...')
    wanted = set(sys.argv[2:])
    items = [item for item in wp.read_export(wp.Path(sys.argv[1]))
             if wp.text_of(item, 'wp:post_type') == 'post' and wp.text_of(item, 'wp:post_id') in wanted]
    found = {wp.text_of(item, 'wp:post_id') for item in items}
    if found != wanted:
        wp.fail(f'エクスポートファイルに見つからない投稿番号があります: {", ".join(sorted(wanted - found))}')

    report = {'comments': 0, 'orphan_comments': 0, 'untitled': 0, 'no_time': 0, 'rewritten_links': 0,
              'unresolved_links': [], 'removed_elements': collections.Counter()}
    for item in sorted(items, key=lambda item: wp.text_of(item, 'wp:post_date')):
        local = wp.text_of(item, 'wp:post_date')
        post_id = next_post_id(local[:10].replace('-', ''))
        front_matter = {'title': html.unescape(wp.text_of(item, 'title')).strip(),
                        'time': local[11:16],
                        'timezone': wp.utc_offset(local, wp.text_of(item, 'wp:post_date_gmt'))}
        categories, tags = wp.categories_and_tags(item)
        if categories:
            front_matter['categories'] = categories
        if tags:
            front_matter['tags'] = tags
        body = wp.convert_content(wp.text_of(item, 'content:encoded'), {}, report)
        wp.write_item(wp.POSTS_DIR / post_id, front_matter, body, wp.extract_comments(item, report))
        print(f'{wp.text_of(item, "wp:post_id")} → {post_id}（{local} {front_matter["title"]}）')


if __name__ == '__main__':
    main()
