#!/usr/local/bin/perl

#=====================================================================
# 題目
#=====================================================================
#   名    称: WwwMail Ver3.26
#   最終更新: 2002年9月8日
#   作 成 者: 杜甫々
#   種    別: フリーソフト（私用・商用を問わず利用・改造・流用・再配布可）
#   最 新 版: http://tohoho.wakusei.ne.jp/

#=====================================================================
# カスタマイズ
#=====================================================================
# ★ perlのパス名
#    このファイルの先頭の１行を、あなたが利用するサーバーにインストー
#    ルされた perl コマンドのパス名に応じて変更してください。例えば、
#    私が加入している BIGLOBE では、#!/usr/local/bin/perl となります。
#    解らない場合は、プロバイダやサーバの管理者にお問い合わせください。
#   「#!」の前には、空文字や空行や他の文字がはいらないようにしてください。

# ★ 送信先メールアドレス
#    $mailto = 'abc@xxx.yyy.zzz'; のようにあなたのメールアドレスに
#    書き換えてください。
$mailto = 'minsyu.5@mis.janis.or.jp';

# ★ サブジェクト(件名)
#    送信されるメールのサブジェクトを指定してください。
$subject = 'message from kato-gaku website';

# ★ メール送信コマンド
#    WebサーバーがUNIXの場合はsendmailコマンド、Windows系の場合はBLATJ.EXE
#    コマンドのパス名を指定（$mailcmd = 'C:\BLATJ\BLATJ.EXE'; など）してく
#    ださい。このコマンドが存在しない場合は、WwwMail は動作しません。また、
#    存在していても、メール送信の設定が行われていない場合があります。詳細
#    はプロバイダやサーバーの管理者にお問い合わせください。
$mailcmd = '/usr/sbin/sendmail';

# ★ 送信結果メッセージ(ヘッダ)
#    <<END_OF_DATA ～ END_OF_DATA の間を好みにあわせて変更してください。
$header = <<END_OF_DATA;
<html>
<head>
<meta http-equiv="Content-type" content="text/html; charset=Shift_JIS">
<title>メール送信結果</title>
</head>
<body>
<h1>メール送信結果</h1>
<hr>
<p>下記のメールを送信しました。ありがとうございました。</p>
<hr>
END_OF_DATA

# ★ 送信結果メッセージ(フッタ)
#    <<END_OF_DATA ～ END_OF_DATA の間を好みにあわせて変更してください。
$footer = <<END_OF_DATA;
<hr>
<a href="/">[戻る]</a>
</body>
</html>
END_OF_DATA

#====================================================================
# 自己診断機能。
#====================================================================
# メール送信がうまく動作しない時に、
# http://～/～/wwwmail.cgi?test の形式で呼び出してください。
if ($ENV{'REQUEST_METHOD'} eq "GET") {
	print "Content-type: text/html; charset=Shift_JIS\n";
	print "\n";
	print "<html>\n";
	print "<head>\n";
	print "<title>WwwMail自己診断</title>\n";
	print "</head>\n";
	print "<body>\n";
	print "<p>CGIは正常に動作しています。</p>\n";
	unless (-f $mailcmd) {
		print "<p>$mailcmd がありません。</p>\n";
	}
	unless (-x $mailcmd) {
		print "<p>$mailcmd が実行可能ではありません。</p>\n";
	}
	unless (-f "jcode.pl") {
		print "<p>jcode.pl がありません。</p>\n";
	}
	unless (-f "mimew.pl") {
		print "<p>mimew.pl がありません。</p>\n";
	}
	print "</body>\n";
	print "</html>\n";
	exit 0;
}

#====================================================================
# 本体
#====================================================================

#
# ライブラリの呼び出し
#
require "jcode.pl";
require "mimew.pl";

#
# 入力値を読み取る
#

	$host = $ENV{'REMOTE_HOST'};
	$addr = $ENV{'REMOTE_ADDR'};

	if ($host eq "" || $host eq $addr) {
		$host = gethostbyaddr(pack("C4", split(/\./, $addr)), 2) || $addr;
	}


if ($ENV{'REQUEST_METHOD'} eq "POST") {
	read(STDIN, $query_string, $ENV{'CONTENT_LENGTH'});
	@a = split(/&/, $query_string);
	foreach $x (@a) {
		($name, $value) = split(/=/, $x);
		$name =~ tr/+/ /;
		$name =~ s/%([0-9a-fA-F][0-9a-fA-F])/pack("C", hex($1))/eg;
		&jcode'convert(*name, "jis");
		$value =~ tr/+/ /;
		$value =~ s/%([0-9a-fA-F][0-9a-fA-F])/pack("C", hex($1))/eg;
		$value =~ s/[\r\n]+/\n/g;
		&jcode'convert(*value, "jis");
		if ($FORM{$name} eq "") {
			$FORM{$name} = $value;
			$FORM[$cnt++] = $name;
		} else {
			$FORM{$name} .= (" " . $value);
		}
	}
}

#
# EMAILが正常なメールあどれすかどうか判断する
#
if ($FORM{'EMAIL'} =~ /^[-_\.a-zA-Z0-9]+\@[-_\.a-zA-Z0-9]+$/) {
	$mailfrom = $FORM{'EMAIL'};
}

#
# メールヘッダを作成する
#
{
	&jcode'convert(*subject, "jis");
	$mailhead = "";
	$mailhead .= "Content-Type: text/plain; charset=\"iso-2022-jp\"\n";
	$mailhead .= "Content-Transfer-Encoding: 7bit\n";
	$mailhead .= "MIME-Version: 1.0\n";
	$mailhead .= "To: $mailto\n";
	if ($mailfrom) {
		$mailhead .= "From: $FORM{'EMAIL'}\n";
		$mailhead .= "Cc: $FORM{'EMAIL'}\n";
	} else {
		$mailhead .= "From: $mailto\n";
	}
	$mailhead .= "Subject: $subject\n";
	$mailhead .= "\n";
}

#
# メールボディを作成する
#
{
	for ($i = 0; $i < $cnt; $i++) {
		$mailbody .= "■$FORM[$i]\n$FORM{$FORM[$i]}\n";
	}

	$mailbody .= "$host\n";

	# "." のみの行は ". " に変換する。
	# 2回繰り返さないと、2行連続で "." のみの行に対応できない
	# "." を ".." に変換する処理が一般的だそうだが、あえて、
	# "." を ". " に変換する。
	$mailbody =~ s/(^|\n)\.(\n|$)/$1. $2/g;
	$mailbody =~ s/(^|\n)\.(\n|$)/$1. $2/g;
}

#
# メールを送信する
#
if ($mailcmd =~ /sendmail/) {
	unless (open(OUT, "| $mailcmd -t")) {
		&errexit("メールの送信に失敗しました。(1)");
	}
	unless (print OUT &mimeencode($mailhead)) {
		&errexit("メールの送信に失敗しました。(2)");
	}
	unless (print OUT $mailbody) {
		&errexit("メールの送信に失敗しました。(3)");
	}
	close(OUT);
} elsif ($mailcmd =~ /BLAT/i) {
	&jcode'convert(*subject, "sjis");
	$cmd = "$mailcmd";
	$cmd .= " -";
	$cmd .= " -t $mailto";
	$cmd .= " -s \"$subject\"";
	if ($mailfrom) {
		$cmd .= " -c $mailfrom";
		$cmd .= " -f $mailfrom";
	}
	unless (open(OUT, "| $cmd > NUL:")) {
		&errexit("メールの送信に失敗しました。(4)");
	}
	&jcode'convert(*mailbody, "sjis");
	unless (print OUT $mailbody) {
		&errexit("メールの送信に失敗しました。(5)");
	}
	&jcode'convert(*mailbody, "jis");
	close(OUT);
} else {
	&errexit("メール送信コマンド $mailcmd が存在しません。");
}

#
# ブラウザ画面に送信結果を書き出す
#
{
	&jcode'convert(*header, "sjis");
	&jcode'convert(*footer, "sjis");

	$mail = $mailhead . $mailbody;
	&jcode'convert(*mail, "euc");
	$mail =~ s/&/&amp;/g;
	$mail =~ s/"/&quot;/g;
	$mail =~ s/</&lt;/g;
	$mail =~ s/>/&gt;/g;
	$mail =~ s/\n/<BR>/g;
	&jcode'convert(*mail, "sjis");

	print "Content-type: text/html\n";
	print "\n";
	print "$header\n";
	print "$mail\n";
	print "$footer\n";
}

#
# エラーメッセージを出力して終了
#
sub errexit {
	local($err) = @_;
	local($msg);

	$msg  = "Content-type: text/html\n";
	$msg .= "\n";
	$msg .= "<html>\n";
	$msg .= "<head>\n";
	$msg .= "<meta http-equiv=\"Content-type\" content=\"text/html; charset=Shift_JIS\">\n";
	$msg .= "<title>メール送信結果</title>\n";
	$msg .= "</head>\n";
	$msg .= "<body>\n";
	$msg .= "<h1>メール送信結果</h1>\n";
	$msg .= "<hr>\n";
	$msg .= "<p>$err</p>\n";
	$msg .= "<p>ブラウザの [戻る] ボタンで戻ってください。</p>\n";
	$msg .= "<hr>\n";
	$msg .= "</body>\n";
	$msg .= "</html>\n";

	&jcode'convert(*msg, "sjis");

	print $msg;

	exit(0);
}
