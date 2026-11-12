/*
  サイト内検索

  build.py が書き出した search.json（全記事のタイトルと本文）を読み込み、
  ブラウザの中で検索する。サーバーや外部の検索サービスは使わない。

  検索の規則
    - 入力した言葉を含む記事を探す（部分一致。日本語でも区切りを気にせず検索できる）
    - 空白で区切った複数の言葉は、すべてを含む記事だけを表示する
    - 全角・半角、英字の大文字・小文字は区別しない
    - 結果は新しい記事から順に表示する

  処理の流れ
    1. URL の ?q= から検索する言葉を取り出す        readQuery()
    2. search.json を読み込み、検索用に整える         loadIndex()
    3. 条件に合う記事を探す                           search()
    4. 結果を表示する                                 showResults()
*/

const PAGE_SIZE = 50;           // 1回に表示する件数
const SNIPPET_LENGTH = 80;      // 結果に表示する本文の抜粋の文字数

const script = document.currentScript;
const input = document.getElementById('search-input');
const statusLine = document.getElementById('search-status');
const resultList = document.getElementById('search-results');
const moreButton = document.getElementById('search-more');


// 比較用に文字列をそろえる：全角英数字を半角に、英字を小文字にする（NFKC 正規化）
function normalize(text) {
  return text.normalize('NFKC').toLowerCase();
}


// 1. URL の ?q= から、検索する言葉のリストを取り出す
function readQuery() {
  const query = new URLSearchParams(location.search).get('q') || '';
  input.value = query;
  return normalize(query).split(/\s+/).filter(term => term !== '');
}


// 2. search.json を読み込み、各記事に比較用の文字列を付け加える
async function loadIndex() {
  const response = await fetch(script.dataset.index);
  if (!response.ok) {
    statusLine.textContent = '検索用のデータを読み込めませんでした。';
    return [];
  }
  const entries = await response.json();
  return entries.map(([path, title, date, text]) => ({
    path, title, date, text,
    target: normalize(title + ' ' + text),
  }));
}


// 3. すべての言葉を含む記事を返す
function search(entries, terms) {
  return entries.filter(entry => terms.every(term => entry.target.includes(term)));
}


// 本文のうち、最初の言葉が現れるあたりを抜き出す
function makeSnippet(entry, term) {
  const position = normalize(entry.text).indexOf(term);
  const start = Math.max(0, position - SNIPPET_LENGTH / 4);
  const snippet = entry.text.slice(start, start + SNIPPET_LENGTH);
  return (start > 0 ? '…' : '') + snippet + (start + SNIPPET_LENGTH < entry.text.length ? '…' : '');
}


// 結果の1件分の要素を作る。記事の文字列は textContent で入れ、HTML として解釈させない
function makeResultItem(entry, term) {
  const item = document.createElement('li');

  const link = document.createElement('a');
  link.href = entry.path;
  link.textContent = entry.title;
  item.appendChild(link);

  if (entry.date) {
    const date = document.createElement('time');
    date.textContent = entry.date;
    item.appendChild(date);
  }

  const snippet = document.createElement('p');
  snippet.textContent = makeSnippet(entry, term);
  item.appendChild(snippet);
  return item;
}


// 4. 結果を PAGE_SIZE 件ずつ表示する。「さらに表示」で続きを出す
function showResults(results, terms) {
  statusLine.textContent = `${results.length} 件見つかりました。`;
  let shown = 0;

  function showNext() {
    for (const entry of results.slice(shown, shown + PAGE_SIZE)) {
      resultList.appendChild(makeResultItem(entry, terms[0]));
    }
    shown = Math.min(shown + PAGE_SIZE, results.length);
    moreButton.hidden = shown >= results.length;
  }

  moreButton.addEventListener('click', showNext);
  showNext();
}


async function main() {
  const terms = readQuery();
  if (terms.length === 0) {
    return;
  }
  statusLine.textContent = '検索しています…';
  const entries = await loadIndex();
  if (entries.length === 0) {
    return;
  }
  showResults(search(entries, terms), terms);
}

main();
