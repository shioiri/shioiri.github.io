<?php
$GLOBALS['START'] = array_sum(explode(' ', microtime()));

mb_internal_encoding('SJIS');
mb_http_output('SJIS');
mb_detect_order('ASCII,JIS,UTF-8,EUC-JP,SJIS');

$GLOBALS['VERSION'] = '1.20';
$GLOBALS['WEBSITE'] = 'www.lovpop.net/apricot/';
$GLOBALS['LOGMAX'] = 1000;    // ログの長さ (稼動中でも変更可能, 標準:500～1000)
$GLOBALS['COOKIE'] = 5184000; // Cookie 寿命 (sec)
$GLOBALS['COL1x'] = '3F74B5'; // カラー濃
$GLOBALS['COL2x'] = '7FC4F9'; // カラー薄
$GLOBALS['COL1s'] = 'E40061'; // カラー濃 シャア専用
$GLOBALS['COL2s'] = 'FFBDD3'; // カラー薄 シャア専用
$GLOBALS['ARRAY_VCNT'] = array(2, 5, 10, 20, 50, 100);           // (times)
$GLOBALS['ARRAY_VSPN'] = array(3, 6, 12, 24, 72, 168, 336, 672); // (hours)
$GLOBALS['TITLE_HIST'] = 'ログ';
$GLOBALS['TITLE_TOTL'] = '累計';
$GLOBALS['TITLE_SPAN'] = '解析期間';
$GLOBALS['TITLE_T24H'] = '時間別の推移';
$GLOBALS['TITLE_T28D'] = '過去28日の推移';
$GLOBALS['TITLE_T12M'] = '過去12ヶ月の推移';
$GLOBALS['TITLE_WEEK'] = '曜日別の平均';
$GLOBALS['TITLE_PAGE'] = '閲覧されたページ';
$GLOBALS['TITLE_REFR'] = 'リファラ';
$GLOBALS['TITLE_VCNT'] = '来訪回数';
$GLOBALS['TITLE_VSPN'] = '来訪間隔';
$GLOBALS['TITLE_HOST'] = '来訪者のドメイン';
$GLOBALS['TITLE_AREA'] = '都道府県';
$GLOBALS['TITLE_SWD1'] = '検索フレーズ';
$GLOBALS['TITLE_SWD2'] = '検索ワード';
$GLOBALS['TITLE_ENGN'] = '検索エンジン';
$GLOBALS['TITLE_AGNT'] = 'ブラウザ';
$GLOBALS['TITLE_OSYS'] = 'OS';
$GLOBALS['TITLE_LANG'] = '言語';
$GLOBALS['TITLE_SCRS'] = '画面サイズ';
$GLOBALS['TITLE_SCRD'] = '画面色深度';

main();

function main()
{
	extract($GLOBALS);
	if ($url = get_param('j'))
	{
		$url = 'http://' . rawurldecode($url);
		$html =<<< EOD
<html>
<head>
	<meta http-equiv="refresh" content="0;URL=$url" />
</head>
<body>
	<code>$url</code>
</body>
</html>
EOD;
		echo $html;
		exit;
	}
	$GLOBALS['ADMIN'] = $ADMIN = 'admin';
	$GLOBALS['BASE'] = $BASE = basename(__FILE__);
	$GLOBALS['URL'] = $URL = "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]";
	$GLOBALS['SCRIPT_DIR'] = dirname($_SERVER[SCRIPT_NAME]);
	$GLOBALS['USE_GIF'] = null;
	if (isset($_SERVER['HTTP_USER_AGENT']))
	{
		$GLOBALS['USE_GIF'] = ereg('DoCoMo', $_SERVER['HTTP_USER_AGENT']);
	}
	if (! is_dir("$BASE.data"))
	{
		if (isset($_GET['m']) && $_GET['m'] == 'init')
		{
			mkdir("$BASE.data", 0700);
			mkdir("$BASE.data/$ADMIN", 0700);
			create_image($ADMIN, $GLOBALS['COL1s'], $GLOBALS['COL2s']);
			header("Location: $URL");
			exit;
		}
		else
		{
			$html =<<< EOD
<html>
<head></head>
<body>
	<code>$BASE</code> の初期化処理を行いますか？<br />
	<br />
	<a href="$BASE?m=init">Yes</a>
</body>
</html>
EOD;
			echo $html;
			exit;
		}
	}
	$GLOBALS['M'] = $M = get_param('m');
	$GLOBALS['U'] = $U = get_param('u');
	$USERS = array();
	$dir_main = opendir("$BASE.data");
	while (($ent = readdir($dir_main)) !== false)
	{
		if (is_dir("$BASE.data/$ent") && $ent{0}!='.')
		{
			$USERS[] = $ent;
		}
	}
	closedir($dir_main);
	$GLOBALS['USERS'] = $USERS;
	$user_is_null = ($U == null);
	$user_invalid = ($U && ! in_array($U, $USERS));
	if ($user_is_null || $user_invalid)
	{
		$GLOBALS['U'] = $U = $ADMIN;
	}
	$lines = io_read($U, 'cnfg.txt');
	while (count($lines) < 9)
	{
		array_push($lines, null);
	}
	$GLOBALS['PASS'] = $lines[0] ? $lines[0] : md5('');
	$GLOBALS['MAILADDR'] = $lines[1];
	$GLOBALS['PFX'] = $lines[2] = ereg_replace('https?://', null, $lines[2]);
	$pfx = str_replace('.', '\\.', str_replace('-', '\\-', $lines[2]));
	$pfx = ereg_replace('[[:space:]]+', ' ', str_replace('*', '[^/]+', $pfx));
	$GLOBALS['PFXS'] = $pfx ? explode(' ', $pfx) : array();
	$cols = explode(',', $lines[3]);
	while (count($cols) < 3)
	{
		array_push($cols, null);
	}
	if (ereg('#?([0-9A-F]{6})', $cols[1], $r))
	{
		$GLOBALS['COL1'] = $r[1];
	}
	else
	{
		$GLOBALS['COL1'] = ($U == $ADMIN) ? $COL1s : $COL1x;
	}
	if (ereg('#?([0-9A-F]{6})', $cols[2], $r))
	{
		$GLOBALS['COL2'] = $r[1];
	}
	else
	{
		$GLOBALS['COL2'] = ($U == $ADMIN) ? $COL2s : $COL2x;
	}
	$GLOBALS['AUTH'] = $lines[4];
	$GLOBALS['REDT'] = $lines[5];
	$GLOBALS['UNIQ'] = $lines[6] ? $lines[6] : 12;
	$GLOBALS['RFPF'] = $lines[7];
	$GLOBALS['RFEG'] = $lines[8];
	switch ($M)
	{
		case 'logo';
			header('Content-Type: image/png');
			readfile("$BASE.data/$U/logo.png");
			exit;
		case 'cube';
			header('Content-Type: image/png');
			readfile("$BASE.data/$U/cube.png");
			exit;
		case 'mini';
			$ext = $GLOBALS['USE_GIF'] ? 'gif' : 'png';
			header("Content-Type: image/$ext");
			readfile("$BASE.data/$U/mini.$ext");
			exit;
		case 'phpinfo';
			echo phpinfo();
			exit;
	}
	if ($user_is_null)
	{
		mode_index();
	}
	if ($user_invalid)
	{
		info('エラー', '指定されたユーザは存在しません');
	}
	switch ($M)
	{
		case 'c':
			mode_count();
			break;
		case null:
			mode_home();
			break;
		case 'auth':
			mode_issue_auth();
			break;
		case 'hist':
			mode_hist();
			break;
		case 't24h':
			mode_t24h();
			break;
		case 'cnfg':
			mode_cnfg();
			break;
		case 'cnfg_set':
			mode_cnfg_set();
			break;
		case 'cnfg_reset':
			mode_cnfg_reset();
			break;
		case 'set':
			mode_set();
			break;
		case 'help':
			mode_help();
			break;
		case 'debug':
			mode_count(true);
			break;
		case 'uninstall':
			mode_uninstall();
			break;
		default:
			mode_graph();
			break;
	}
	info('エラー', 'パラメータが間違っています');
}

function mode_count($debug=false)
{
	extract($GLOBALS);
	$debug_log = array();
	$now = time();
	list($c_rev, $c_count, $c_last) = read_cookie();
	$c_id = read_cookie_id();
	if ($c_count<0)
	{
		$msg = 'カウントが禁止されています';
		$debug ? $debug_log[] = $msg : exits(null);
	}
	if ($now-$c_last < $UNIQ*3600)
	{
		$msg = '来訪間隔が短すぎます (Cookie)';
		$debug ? $debug_log[] = $msg : exits(null);
	}
	$q_addr = $q_page = $q_agnt = $q_refr = $q_scrn = $q_host = $q_lang = null;
	if (isset($_SERVER['REMOTE_ADDR']))
	{
		$q_addr = $_SERVER['REMOTE_ADDR'];
		$q_host = gethostbyaddr($q_addr);
		if ($q_host == $q_addr)
		{
			$q_host = null;
		}
	}
	else
	{
		$msg = 'IP アドレスが取得できません';
		$debug ? $debug_log[] = $msg : exits(null);
	}
	if (isset($_SERVER['HTTP_REFERER']))
	{
		$q_page = $_SERVER['HTTP_REFERER'];
	}
	if (isset($_SERVER['HTTP_USER_AGENT']))
	{
		$q_agnt = $_SERVER['HTTP_USER_AGENT'];
	}
	if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
	{
		$q_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
		if (strlen($q_lang) > 2)
		{
			$q_lang = substr($q_lang, 0, 2);
		}
	}
	if (isset($_GET['refr']))
	{	
		$q_refr = $_GET['refr'];
	}
	if (isset($_GET['scrn']))
	{
		$q_scrn = $_GET['scrn'];
	}
	if (isset($_GET['navi']) && $_GET['navi'])
	{
		$q_agnt = $_GET['navi'];
	}
	$q_agnt = htmlentities($q_agnt);
	if (! ereg('^https?://', $q_refr))
	{
		$q_refr = null;
	}
	if (! ereg('^([0-9]{3,4}x){2}[0-9]{1,2}$', $q_scrn))
	{
		$q_scrn = null;
	}
	if ($q_page && count($PFXS))
	{
		$match = false;
		if (! ereg('^https?://(' . implode('|', $PFXS) . ')', $q_page))
		{
			$msg = '不許可のリファラです';
			$debug ? $debug_log[] = $msg : exits(null);
		}
	}
	if (($q_page && ! eregi($_SERVER['SCRIPT_NAME'], $q_page)) || $U==$ADMIN)
	{
		io_rotate($U, 'page.txt', $q_page, $LOGMAX);
	}
	$envs = array(
		'HTTP_CACHE_CONTROL', 'HTTP_CACHE_INFO',
		'HTTP_CLIENT_IP',
		'HTTP_FORWARDED', 'HTTP_FROM',
		'HTTP_IF_MODIFIED_SINCE', 'HTTP_MAX_FORWARDS',
		'HTTP_REMOTE_HOST_WP',
		'HTTP_PROXY_AUTHORIZATION', 'HTTP_PROXY_CONNECTION',
		'HTTP_SP_HOST',
		'HTTP_TE', 'HTTP_VIA',
		'HTTP_X_CISCO_BBSM_CLIENTIP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_HTX_AGENT',
		'HTTP_X_LOCKING',
		'HTTP_XONNECTION', 'HTTP_XROXY_CONNECTION',
	);
	$via_proxy = false;
	foreach ($envs as $env)
	{
		if ($via_proxy = isset($_SERVER[$env]))
		{
			break;
		}
	}
	$r_addr = $r_host = null;
	$x_addr = $x_host = null;
	if ($via_proxy)
	{
		$envs = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_CACHE_CONTROL',
			'HTTP_FORWARDED',
			'HTTP_REMOTE_HOST_WP',
			'HTTP_SP_HOST',
			'HTTP_VIA',
			'HTTP_X_CISCO_BBSM_CLIENTIP',
		);
		foreach ($envs as $env)
		{
			if (! isset($_SERVER[$env]))
			{
				continue;
			}
			if (ereg('([0-9]{1,3}\.){3}[0-9]{1,3}$', $_SERVER[$env], $r))
			{
				$r_addr = $r[0];
				if (! ereg('^(192|127)\.', $r_addr))
				{
					$r_host = gethostbyaddr($r_addr);
				}
				break;
			}
		}
		if (! $r_addr && isset($_SERVER[$env = 'HTTP_FORWARDED']))
		{
			if (eregi('for ([a-z0-9_\-\.]+)$', $_SERVER[$env], $r))
			{
				$r_addr = gethostbyname($r_host = $r[1]);
				if (! $r_addr)
				{
					$r_host = null;
				}
				break;
			}
		}
		if ($r_addr == $r_host)
		{
			$r_host = null;
		}
	}
	if ($via_proxy || eregi('proxy|cache|gateway|keeper', $q_host))
	{
		$x_addr = $q_addr;
		$x_host = $q_host;
		$q_addr = $r_addr;
		$q_host = $r_host;
	}
	$lines = io_read($U, 'hist.txt');
	foreach ($lines as $line)
	{
		if (! $line)
		{
			break;
		}
		$line = explode('<>', $line);
		list($hq_epoc, $hq_addr, $hx_addr,,,, $hq_agnt, $hc_id) = $line;
		if ($now - $hq_epoc > $UNIQ*3600)
		{
			break;
		}
		if ($c_id == $hc_id)
		{
			break;
		}
		$isSameIP = ($hq_addr && $hq_addr==$q_addr);
		$isSamePx = (! $q_addr && $x_addr && $x_addr==$hx_addr);
		if (($isSameIP || $isSamePx) && $hq_agnt==$q_agnt)
		{
			$msg = '来訪間隔が短すぎます (LOG)';
			$debug ? $debug_log[] = $msg : exits(null);
		}
	}
	//{
	//}
	$refr = rawurldecode($q_refr);
	if ($index = strpos($refr, '#'))
	{
		$refr = substr($refr, 0, $index);
	}
	if ($refr && $refr{$index = strlen($refr) - 1} == '?')
	{
		$refr = substr($refr, 0, $index);
	}
	$engines = array(
		'^http://([^/]*(yahoo)\.[^/]+).+p=([^&]+)',
		'^http://([^/]*(google|yahoo|msn|biglobe|nifty)\.[^/]+).+q=([^&]+)',
		'^http://([^/]*(excite)\.[^/]+).+search=([^&]+)',
		'^http://([^/]*(excite)\.[^/]+).+s=([^&]+)',
		'^http://([^/]*(goo)\.[^/]+).+MT=([^&]+)',
		'^http://([^/]*(nifty)\.[^/]+).+Text=([^&]+)',
		'^http://([^/]*(fresheye)\.[^/]+).+kw=([^&]+)',
		'^http://([^/]*(infoseek)\.[^/]+).+qt=([^&]+)',
		'^http://([^/]*(pagesupli)\.[^/]+)/websearch/([^/]+)',
		'^http://([^/]*(naver|aol)\.[^/]+).+query=([^&]+)',
		'^http://([^/]*(alltheweb|altavista)\.[^/]+).+q=([^&]+)',
		'^http://([^/]*(livedoor|ceek|nttrd|pagesupli)\.[^/]+).+q=([^&]+)',
		'^http://([^/]*(cometsystems)\.[^/]+).+qry=([^&]+)',
		'^http://([^/]*(cafesta)\.[^/]+).+Keywords=([^&]+)',
		'^http://([^/]*(marsflag)\.[^/]+).+key=([^&]+)',
	);
	$egnl = $egns = $phrs = null;
	foreach ($engines as $engine)
	{
		if (ereg($engine, $refr, $regs))
		{
			$egnl = $regs[1];
			$egns = $regs[2];
			$phrs = $regs[3];
			$refr = $RFEG ? $egns : null;
			if (ereg('cache:[-_a-zA-Z0-9]+:([^ \+]+)', $phrs, $regs))
			{
				$phrs = null;
				$refr = 'http://' . $regs[1];
				break;
			}
			if (ereg('http://images.google', $egnl))
			{
				$phrs = null;
				break;
			}
			if ($egns == 'pagesupli')
			{
				$str = null;
				while (eregi('^[0-9a-f]{2}', $phrs, $regs))
				{
					$str .= '%' . $regs[0];
					$phrs = substr($phrs, 2);
				}
				$phrs = rawurldecode($str);
			}
			$phrs = mb_convert_encoding($phrs, 'SJIS', mb_detect_encoding($phrs));
			while (eregi('%u([0-9a-f]{4})', $phrs, $regs))
			{
				$ucs2 = mb_convert_encoding(pack('H4', $regs[1]), 'SJIS', 'UCS-2');
				$phrs = str_replace($regs[0], $ucs2, $phrs);
			}
			$phrs = mb_convert_kana($phrs, 'asKV');
			if (function_exists('mb_strtolower'))
			{
				$phrs = mb_strtolower($phrs);
			}
			else
			{
				for ($i=0; $i<mb_strlen($phrs); $i++)
				{
					$c = mb_substr($phrs, $i, 1);
					if (ereg('^[A-Z]$', $c))
					{
						$c = mb_substr($phrs, 0, $i) . strtolower($c);
						if ($i != mb_strlen($phrs)-1)
						{
							$c .= mb_substr($phrs, $i+1);
						}
						$phrs = $c;
					}
				}
			}
			$phrs = trim(ereg_replace(' +', ' ', ereg_replace('\+', ' ', $phrs)));
			asort($phrs = explode(' ', $phrs));
			$phrs = implode(' ', $phrs);
			$phrs = str_replace('<', '&lt;', str_replace('>', '&gt;', $phrs));
			break;
		}
	}
	if (! $debug)
	{
		if ($phrs)
		{
			$test_url = $_GET['refr'];
			io_rotate($U, 'srch.txt', "$egnl<>$egns<>$phrs", $LOGMAX);
		}
		if (ereg('\.([^\.]+)\.(ocn\.ne|mesh\.ad)\.jp', $q_host, $regs))
		{
			$names = array();
			if ($regs[2] == 'ocn.ne')
			{
				$names = array(
'hokkaido'=>'北海道','aomori'=>'青森','iwate'=>'岩手','miyagi'=>'宮城',
'akita'=>'秋田','yamagata'=>'山形','fukushima'=>'福島','ibaraki'=>'茨城',
'tochigi'=>'栃木','gunma'=>'群馬','saitama'=>'埼玉','chiba'=>'千葉',
'tokyo'=>'東京','kanagawa'=>'神奈川','niigata'=>'新潟','toyama'=>'富山',
'ishikawa'=>'石川','fukui'=>'福井','yamanashi'=>'山梨','nagano'=>'長野',
'gifu'=>'岐阜','shizuoka'=>'静岡','aichi'=>'愛知','mie'=>'三重',
'shiga'=>'滋賀','kyoto'=>'京都','osaka'=>'大阪','hyogo'=>'兵庫',
'nara'=>'奈良','wakayama'=>'和歌山','tottori'=>'鳥取','shimane'=>'島根',
'okayama'=>'岡山','hiroshima'=>'広島','yamaguchi'=>'山口',
'tokushima'=>'徳島','kagawa'=>'香川','ehime'=>'愛媛','kochi'=>'高知',
'fukuoka'=>'福岡','saga'=>'佐賀','nagasaki'=>'長崎','kumamoto'=>'熊本',
'oita'=>'大分','miyazaki'=>'宮崎','kagoshima'=>'鹿児島','okinawa'=>'沖縄',
				);
			}
			else
			{
				$names = array(
'hkd'=>'北海道','aom'=>'青森','iwa'=>'岩手','myg'=>'宮城','aki'=>'秋田',
'ygt'=>'山形','fks'=>'福島','iba'=>'茨城','tcg'=>'栃木','gnm'=>'群馬',
'stm'=>'埼玉','chb'=>'千葉','tky'=>'東京','tk0'=>'東京','tk1'=>'東京',
'tk2'=>'東京','tk3'=>'東京','kng'=>'神奈川','nig'=>'新潟','tym'=>'富山',
'isk'=>'石川','fki'=>'福井','ymn'=>'山梨','ngn'=>'長野','gif'=>'岐阜',
'szo'=>'静岡','aic'=>'愛知','mie'=>'三重','sig'=>'滋賀','kyt'=>'京都',
'osk'=>'大阪','os0'=>'大阪','os1'=>'大阪','hyg'=>'兵庫','nra'=>'奈良',
'wky'=>'和歌山','ttr'=>'鳥取','smn'=>'島根','oky'=>'岡山','hrs'=>'広島',
'ygc'=>'山口','tks'=>'徳島','kgw'=>'香川','ehm'=>'愛媛','koc'=>'高知',
'fko'=>'福岡','sag'=>'佐賀','ngs'=>'長崎','kmm'=>'熊本','oit'=>'大分',
'myz'=>'宮崎','kgs'=>'鹿児島','okn'=>'沖縄',
				);
			}
			if (isset($names[$regs[1]]))
			{
				io_rotate($U, 'area.txt', $names[$regs[1]], $LOGMAX);
			}
		}
		$c_count = update_vcnt($c_rev, $c_count, $now);
		if ($refr)
		{
			if (count($PFXS) && ! $RFPF)
			{
				$pattern = '^https?://(' . implode('|', $PFXS) . ')';
				if (! ereg($pattern, $refr))
				{
					io_rotate($U, 'refr.txt', $refr, $LOGMAX);
				}
			}
			else
			{
				io_rotate($U, 'refr.txt', $refr, $LOGMAX);
			}
		}
		$vspn = $c_last ? ($now-$c_last) : 0;
		$data = "$now<>$q_addr<>$x_addr<>$q_host<>$x_host<>$q_refr<>$q_agnt<>";
		$data .= "$q_scrn<>$c_count<>$vspn<>$q_lang<>$c_id";
		io_rotate($U, 'hist.txt', $data, $LOGMAX);
		update_data($U);
		if ($U==$ADMIN && ereg('u?=([^&]+)', $q_page, $regs))
		{
			if (in_array($regs[1], $USERS))
			{
				update_view($regs[1]);
			}
		}
	}
	if ($debug)
	{
		$cookie = isset($_COOKIE[$U]) ? $_COOKIE[$U] : 'null';
		$c_id = isset($_COOKIE['__id']) ? $_COOKIE['__id'] : 'null';
		$debug_log = implode("<br />\n", $debug_log);
		$html =<<< EOD
<code>
	\$q_addr = $q_addr<br />
	\$q_host = $q_host<br />
	\$q_page = $q_page<br />
	\$q_refr = $q_refr<br />
	\$q_agnt = $q_agnt<br />
	\$q_lang = $q_lang<br />
	\$q_scrn = $q_scrn<br />
	\$refr = $refr<br />
	\$egnl = $egnl<br />
	\$egns = $egns<br />
	\$phrs = $phrs<br />
	\$x_addr = $x_addr<br />
	\$x_host = $x_host<br />
	\$c_id = $c_id<br />
	\$cookie = $cookie<br />
	$debug_log<br />
</code>
EOD;
		info('DEBUG', $html, $debug);
	}
	exits('ok');
}

function request_pass($allow_null=false)
{
	extract($GLOBALS);
	if (isset($_POST['init']) && $_POST['init']=='on')
	{
		return;
	}
	if ($PASS == md5(''))
	{
		exits(uipc_initialize());
	}
	if (! $AUTH)
	{
		return;
	}
	$code = isset($_COOKIE["_$U"]) ? $_COOKIE["_$U"] : null;
	if ($code == $PASS)
	{
		return;
	}
	else
	{
		exits(uipc_auth());
	}
}

function check_pass()
{
	extract($GLOBALS);
	$success = false;
	if (isset($_POST['pass']))
	{
		$received_pass = md5($_POST['pass']);
		$success = ($received_pass == $PASS);
		if ($U!=$ADMIN && ! $success)
		{
			$lines = io_read($ADMIN, 'cnfg.txt');
			$admin_pass = count($lines) > 1 ? $lines[0] : md5('');
			$success = ($received_pass == $admin_pass);
		}
	}
	if (! $success)
	{
		info('エラー', 'パスワードが違います');
	}
	return;
}

function mode_issue_auth()
{
	extract($GLOBALS);
	$success = false;
	check_pass();
	$mm = (isset($_POST['mm']) && $_POST['mm']) ? '&m=' . $_POST['mm'] : null;
	setcookie("_$U", $PASS, null, $SCRIPT_DIR, $_SERVER['HTTP_HOST']);
	header("Location: $URL?u=$U$mm");
	exit;
}

function mode_home()
{
	extract($GLOBALS);
	if ($U == $ADMIN)
	{
		mode_index();
	}
	request_pass();
	$params = array();
	$params['page'] = order_url($pages = io_read($U, 'page.txt'));
	$params['target_url'] = $pages[0];
	list($epocs, $epocp, $total, $month, $hours, $span)
		= format_data(io_read($U, 'data.txt'));
	list($dayAve, $totalAve) = order_ave($epocs, $total, $hours, $span);
	$params['epocs'] = $epocs;
	$params['hours'] = $hours;
	$params['str_start'] = date('y/m/d', $epocs);
	$params['str_total'] = number_format($total);
	$params['str_span'] = number_format($span);
	$params['dayAve'] = $dayAve;
	list($t24hAve, $t24hMax) = order_t24h($epocs, $hours, $span);
	$params['t24hAve'] = $t24hAve;
	$params['t24hMax'] = $t24hMax;
	list($day, $dayMax, $dayMin)
		= order_t28d($epocs, $total, $hours, $dayAve, $totalAve);
	$params['day'] = $day;
	$params['dayMax'] = $dayMax;
	$params['dayMin'] = $dayMin;
	$params['tag'] = tag_pc($ADMIN);
	list($month, $monthMax, $monthMin, $monthAve) = order_t12m($month);
	$params['month'] = $month;
	$params['monthAve'] = $monthAve;
	$params['monthMax'] = $monthMax;
	$params['monthMin'] = $monthMin;
	list($week, $weekMax, $weekMin)
		= order_week($epocs, $hours, $span, $dayAve);
	$params['week'] = $week;
	$params['weekMax'] = $weekMax;
	$params['weekMin'] = $weekMin;
	list($rev, $vcnt) = format_vcnt(io_read($U, 'vcnt.txt'));
	list($vcnt, $vcntMax) = order_vcnt($vcnt);
	$params['vcnt'] = $vcnt;
	$params['vcntMax'] = $vcntMax;
	$params['vcntRev'] = date('y/m/d', $rev);
	$params['area'] = array_count_values(io_read($U, 'area.txt'));
	$params['refr'] = order_url(io_read($U, 'refr.txt'));
	list($engn, $phrs, $word) = read_format_srch();
	$params['engn'] = $engn;
	$params['phrs'] = $phrs;
	$params['word'] = $word;
	list($host, $agnt, $osys, $scrs, $scrd, $vspn, $java, $lang)
		= read_format_hist();
	list($vspn, $vspnMax) = order_vspn($vspn);
	$params['host'] = $host;
	$params['agnt'] = $agnt;
	$params['osys'] = $osys;
	$params['scrs'] = $scrs;
	$params['scrd'] = $scrd;
	$params['vspn'] = $vspn;
	$params['java'] = $java;
	$params['lang'] = $lang;
	$params['vspnMax'] = $vspnMax;
	exits(uipc_home($params));
}

function mode_graph()
{
	extract($GLOBALS);
	request_pass();
	$hash = $title = null;
	$align = 'left';
	if ($M=='page' || $M=='refr' || $M=='area')
	{
		switch ($M)
		{
			case 'page':
				$hash = order_url(io_read($U, 'page.txt'));
				$title = $TITLE_PAGE;
				break;
			case 'refr':
				$hash = order_url(io_read($U, 'refr.txt'));
				$title = $TITLE_REFR;
				break;
			case 'area':
				$hash = array_count_values(io_read($U, 'area.txt'));
				$title = $TITLE_AREA;
				$align = 'right';
				break;
		}
		arsort($hash);
	}
	else if ($M=='engn' || $M=='phrs' || $M=='word')
	{
		list($engn, $phrs, $word) = read_format_srch();
		switch ($M)
		{
			case 'engn':
				$hash = $engn;
				$title = $TITLE_ENGN;
				$hash = gsort($hash);
				break;
			case 'phrs':
				$hash = $phrs;
				$title = $TITLE_SWD1;
				arsort($hash);
				break;
			case 'word':
				$hash = $word;
				$title = $TITLE_SWD2;
				arsort($hash);
				break;
		}
	}
	else
	{
		list($host, $agnt, $osys, $scrs, $scrd, $vspn, $java, $lang)
			= read_format_hist();
		switch ($M)
		{
			case 'host':
				$hash = $host;
				$title = $TITLE_HOST;
				arsort($hash);
				break;
			case 'agnt':
				$hash = $agnt;
				$title = $TITLE_AGNT;
				$hash = gsort($hash);
				break;
			case 'osys':
				$hash = $osys;
				$title = $TITLE_OSYS;
				$hash = gsort($hash);
				break;
			case 'lang':
				$hash = order_lang($lang);
				$title = $TITLE_LANG;
				break;
			case 'scrs':
				$hash = $scrs;
				$title = $TITLE_SCRS;
				$align = 'right';
				arsort($hash);
				break;
			case 'scrd':
				$hash = $scrd;
				$title = $TITLE_SCRD;
				$align = 'right';
				arsort($hash);
				break;
			case 'vspn':
				list($vspn, $vspnMax) = order_vspn($vspn);
				$hash = $vspn;
				$title = $TITLE_SCRS;
				break;
			default:
				info('エラー', 'パラメータが間違っています');
				break;
		}
	}
	exits(uipc_graph($title, $hash, $align));
}

function mode_t24h()
{
	extract($GLOBALS);
	request_pass();
	$offset = get_param('o', 0);
	list($epocs, $epocp, $total, $month, $hours, $span)
		= format_data(io_read($U, 'data.txt'));
	list($t24hAve, $t24hMax) = order_t24h($epocs, $hours, $span, $offset);
	exits(uipc_t24h($hours, $offset, $t24hAve, $t24hMax));
}

function mode_help()
{
	exits(uipc_help());
}

function mode_hist()
{
	extract($GLOBALS);
	request_pass();
	$lines = io_read($U, 'hist.txt');
	$offset = get_param('o', 0);
	exits(uipc_hist($lines, $offset));
}

function mode_cnfg()
{
	extract($GLOBALS);
	if ($U == $ADMIN)
	{
		mode_index();
	}
	request_pass();
	list($c_rev, $c_count, $c_last) = read_cookie();
	exits(uipc_cnfg(tag_pc($U), tag_cp($U), ($c_count==-1)));
}

function mode_cnfg_set()
{
	extract($GLOBALS);
	request_pass();
	check_pass();
	$pxs = isset($_POST['pfx']) ? $_POST['pfx'] : null;
	if ($pxs && $U!=$ADMIN)
	{
		$p = array();
		foreach (explode(' ', trim($pxs)) as $px)
		{
			if (! $px)
			{
				continue;
			}
			$px = strtolower($px);
			if (ereg('^(https?://)?([a-z0-9\*][-_~/%:@0-9a-z\.\*]+)$', $px, $r))
			{
				$p[] = ereg_replace('/$', null, $r[2]);
			}
			else
			{
				info('エラー', 'サーバ名が無効です');
			}
		}
		$tmp = array();
		foreach ($p as $pp) $tmp[$pp] = strlen($pp);
		arsort($tmp);
		$p = array_keys($tmp);
		$PFX = implode(' ', $p);
	}
	else $PFX = null;
	if (isset($_POST['uniq']) && 1<=$_POST['uniq'] && $_POST['uniq']<=24)
	{
		$UNIQ = intval($_POST['uniq']);
	}
	$MAILADDR = isset($_POST['mail']) ? $_POST['mail'] : null;
	if (! eregi('[0-9a-z/._=+-]+@([-0-9a-z]+\.)+[a-z]{2,6}', $MAILADDR))
	{
		info('エラー', "有効なメールアドレスを指定してください<br />$MAILADDR");
	}
	$MAILADDR = str_replace('@', '&#64;', strtolower($MAILADDR));
	$MAILADDR = str_replace('.', '&#46;', strtolower($MAILADDR));
	$AUTH = (isset($_POST['auth']) && $_POST['auth']=='on') ? true : false;
	$REDT = (isset($_POST['redt']) && $_POST['redt']=='on') ? true : false;
	$RFPF = (isset($_POST['rfpf']) && $_POST['rfpf']=='on') ? true : false;
	$RFEG = (isset($_POST['rfeg']) && $_POST['rfeg']=='on') ? true : false;
	$c = isset($_POST['col1']) ? $_POST['col1'] : null;
	$c = strtoupper($c);
	$COL1 = eregi('#?([0-9A-F]{6})', $c , $r) ? $r[1] : $COL1;
	$c = isset($_POST['col2']) ? $_POST['col2'] : null;
	$c = strtoupper($c);
	$COL2 = eregi('#?([0-9A-F]{6})', $c , $r) ? $r[1] : $COL2;
	$pass1 = isset($_POST['pass1']) ? $_POST['pass1'] : null;
	$pass2 = isset($_POST['pass2']) ? $_POST['pass2'] : null;
	if ($pass1 && $pass2)
	{
		if ($pass1 == $pass2)
		{
			$PASS = md5($pass1);
		}
		else
		{
			info('エラー', '２個の新しいパスワードが一致しません');
		}
	}
	else if ($PASS==md5(''))
	{
		info('エラー', '有効なパスワードを指定してください');
	}
	$data = null;
	if ($U==$ADMIN)
	{
		$data = "$PASS\n$MAILADDR\n\n$COL0,$COL1s,$COL2s\n$AUTH\n\n12\n\n\n";
	}
	else
	{
		$data = "$PASS\n$MAILADDR\n$PFX\n$COL0,$COL1,$COL2\n";
		$data .= "$AUTH\n$REDT\n$UNIQ\n$RFPF\n$RFEG";
	}
	$fp = fopen("$BASE.data/$U/cnfg.txt", 'w');
	flock($fp, LOCK_EX);
	fwrite($fp, $data);
	fclose($fp);
	create_image($U, $COL1, $COL2);
	list($c_rev, $c_count, $c_last) = read_cookie();
	if (isset($_POST['ignr']) && $_POST['ignr']=='on')
	{
		$c_count = -1;
	}
	else if ($c_count < 0)
	{
		$c_count = 1;
	}
	setcookie($U, "$c_rev,$c_count,$c_last", time() + $COOKIE,
		$SCRIPT_DIR, $_SERVER['HTTP_HOST']);
	header(($U==$ADMIN) ? "Location: $URL" : "Location: $URL?u=$U&m=cnfg");
	exit;
}

function mode_cnfg_reset()
{
	extract($GLOBALS);
	request_pass();
	check_pass();
	if (! isset($_POST['del_confirm']) || $_POST['del_confirm']!='on')
	{
		header("Location: $URL?u=$U&m=cnfg");
		exit;
	}
	if (isset($_POST['del_data']) && $_POST['del_data']=='on')
	{
		if (is_file($file_name = "$BASE.data/$U/data.txt"))
		{
			unlink($file_name);
		}
	}
	if (isset($_POST['del_page']) && $_POST['del_page']=='on')
	{
		if (is_file($file_name = "$BASE.data/$U/page.txt"))
		{
			unlink($file_name);
		}
	}
	if (isset($_POST['del_refr']) && $_POST['del_refr']=='on')
	{
		if (is_file($file_name = "$BASE.data/$U/refr.txt"))
		{
			unlink($file_name);
		}
	}
	if (isset($_POST['del_vcnt']) && $_POST['del_vcnt']=='on')
	{
		if (is_file($file_name = "$BASE.data/$U/vcnt.txt"))
		{
			unlink($file_name);
		}
	}
	if (isset($_POST['del_srch']) && $_POST['del_srch']=='on')
	{
		if (is_file($file_name = "$BASE.data/$U/srch.txt"))
		{
			unlink($file_name);
		}
	}
	if (isset($_POST['del_area']) && $_POST['del_area']=='on')
	{
		if (is_file($file_name = "$BASE.data/$U/area.txt"))
		{
			unlink($file_name);
		}
	}
	if (isset($_POST['del_hist']) && $_POST['del_hist']=='on')
	{
		if (is_file($file_name = "$BASE.data/$U/hist.txt"))
		{
			unlink($file_name);
		}
	}
	header("Location: $URL?u=$U&m=cnfg");
	exit;
}

function mode_index()
{
	extract($GLOBALS);
	request_pass();
	$hour = date('H');
	$zone = date('Z');
	$size_total = 0;
	$mailtos = $spans = $totals = $ave28s = $viewss = array();
	foreach ($USERS as $user)
	{
		$path_sub = "$BASE.data/$user";
		$dir_sub = opendir($path_sub);
		while (($ent = readdir($dir_sub)) !== false)
		{
			$file_name = "$path_sub/$ent";
			if (is_file($file_name) && $ent{0}!='.')
			{
				$size_total += filesize($file_name);
			}
		}
		closedir($dir_sub);
		$lines = io_read($user, 'cnfg.txt');
		$mailto = isset($lines[1]) ? $lines[1] : '-';
		$mailtos[$user] = str_replace('@', '&#64;', $mailto);
		$mailtos[$user] = str_replace('.', '&#46;', $mailto);
		list($epocs, $epocp, $total, $month, $hours, $span)
			= format_data(io_read($user, 'data.txt'));
		list($dayAve, $totalAve) = order_ave($epocs, $total, $hours, $span);
		$totals[$user] = $total;
		$spans[$user] = $span;
		$ave28s[$user] = $dayAve;
		list($epocp, $views) = format_view(io_read($user, 'view.txt'));
		$viewss[$user] = 0;
		for ($i=0; $i<168; $i++) $viewss[$user] += $views[$i + $hour + 1];
	}
	$params = array();
	$params['span'] = $spans;
	$params['totl'] = $totals;
	$params['av28'] = $ave28s;
	$params['view'] = $viewss;
	$params['addr'] = $mailtos;
	$params['av28_max'] = max(max($ave28s), 1);
	$params['view_max'] = max(max($viewss), 1);
	$params['size_total'] = number_format(intval($size_total/1024));
	$params['view_total'] = number_format(array_sum($viewss));
	list($c_rev, $c_count, $c_last) = read_cookie();
	exits(uipc_index($params, ($c_count==-1)));
}

function mode_set()
{
	extract($GLOBALS);
	if ($U != $ADMIN)
	{
		info('エラー', 'パラメータが間違っています');
	}
	$pass0 = isset($_POST['pass']) ? $_POST['pass'] : null;
	if (md5($pass0) != $PASS)
	{
		info('エラー', '管理者パスワードが違います');
	}
	$user_name = isset($_POST['user_name']) ? $_POST['user_name'] : null;
	if ($user_name)
	{
		$add_del = isset($_POST['add_del']) ? $_POST['add_del'] : null;
		if ($add_del == 'add')
		{
			if (in_array($user_name, $USERS))
			{
				info('エラー', '指定されたユーザはすでに存在します');
			}
			if (! ereg('^[a-zA-Z0-9_]+$', $user_name))
			{
				info('エラー', 'ユーザ名に a～z, A～Z, 0～9, _ 以外の文字は含めません');
			}
			if (! ereg('^[a-zA-Z]+', $user_name))
			{
				info('エラー', 'ユーザ名の頭文字はアルファベットでなくてはいけません');
			}
			mkdir("$BASE.data/$user_name", 0700);
			$colors = array(
				$COL1x, $COL2x, $COL1s, $COL2s, 'E06F24', 'FFDFBD',
				'928434', 'EDDFB5', '558101', 'D7DE88', '338139', 'CDE3BF',
				'2477A6', 'B5E1ED', '484894', 'B5C1ED', 'C23879', 'EDC5DE',
			);
			$i = rand(0, count($colors)/2);
			$col1 = $colors[2*$i];
			$col2 = $colors[2*$i+1];
			$fp = fopen("$BASE.data/$user_name/cnfg.txt", 'w');
			flock($fp, LOCK_EX);
			fwrite($fp, "\n\n\nFFFFFF,$col1,$col2");
			fclose($fp);
			create_image($user_name, $col1, $col2);
		}
		else if ($add_del=='del' && $user_name!=$ADMIN)
		{
			if (in_array($user_name, $USERS))
			{
				$path_sub = "$BASE.data/$user_name";
				$dir_sub = opendir($path_sub);
				while (($ent = readdir($dir_sub)) !== false)
				{
					$file_name = "$path_sub/$ent";
					if (is_file($file_name))
					{
						unlink($file_name);
					}
				}
				closedir($dir_sub);
				$result = rmdir($path_sub);
			}
		}
	}
	header("Location: $URL");
}

function mode_uninstall()
{
	extract($GLOBALS);
	if ($U != $ADMIN)
	{
		info('エラー', 'パラメータが間違っています');
	}
	if (! isset($_POST['uninstall_confirm']) || $_POST['uninstall_confirm']!='on')
	{
		header("Location: $URL");
		exit;
	}
	$pass1 = isset($_POST['pass1']) ? $_POST['pass1'] : null;
	$pass2 = isset($_POST['pass2']) ? $_POST['pass2'] : null;
	if (md5($pass1) != $PASS || md5($pass2) != $PASS)
	{
		info('エラー', '管理者パスワードが違います');
	}
	$dir_main = opendir("$BASE.data");
	while (($ent = readdir($dir_main)) !== false)
	{
		$path_sub = "$BASE.data/$ent";
		if (is_dir($path_sub) && $ent{0}!='.')
		{
			$dir_sub = opendir($path_sub);
			while (($ent_sub = readdir($dir_sub)) !== false)
			{
				$file_name = "$path_sub/$ent_sub";
				if (is_file($file_name))
				{
					unlink($file_name);
				}
			}
			closedir($dir_sub);
			rmdir($path_sub);
		}
	}
	closedir($dir_main);
	$result = rmdir("$BASE.data");
	if (! $result)
	{
		$message =<<< EOD
アクセス権限の都合上、削除できないファイルが存在します。<br />
手動で削除できるファイルを全て削除したのちに、
再度アンインストールを実行してください。<br />
それでも無理な場合はサーバ管理者にお問い合わせください。
EOD;
		info('エラー', $message);
	}
	header("Location: $URL");
	exit;
}

function order_ave($epocs, $total, $hours, $span)
{
	for ($i=0; $i<672; $i++) $total28d += $hours[$i];
	$dayAve = $totalAve = 0;
	if ($span >= 30)
	{
		$dayAve = round($total28d / 28);
		$tmp = 0;
		for ($i=0; $i<24; $i++) $tmp += $hours[672 + $i];
		$totalAve = round(($total - $tmp) / $span);
	}
	else
	{
		$dayAve = $totalAve = round($total28d / max($span, 1));
	}
	return array($dayAve, $totalAve);
}

function order_t24h($epocs, $hours, $span, $offset = 28)
{
	$hour = date('H', $epocs);
	$t24hAve = array_fill(0, 24, 0);
	for ($i=0; $i<672; $i++) $t24hAve[$i % 24] += $hours[$i];
	if ($span >= 28)
	{
		for ($i=0; $i<24; $i++) $t24hAve[$i] /= 28;
	}
	else
	{
		for ($i=0; $i<24; $i++)
		{
			$t24hAve[$i] /= max(1, intval($span) - ($i<$hour ? 1 : 0));
		}
	}
	$t24hMax = max($t24hAve);
	for ($i=0; $i<24; $i++) $t24hMax = max($t24hMax, $hours[$offset*24 + $i]);
	$t24hMax = max(1, $t24hMax);
	return array($t24hAve, $t24hMax);
}

function order_t28d($epocs, $total, $hours, $dayAve, $totalAve)
{
	for ($i=0; $i<672; $i++) $total28d += $hours[$i];
	$day = array_fill(0, 29, 0);
	$dayMin = $dayMax = $totalAve;
	for ($i=0; $i<29; $i++)
	{
		for ($j=0; $j<24; $j++) $day[$i] += $hours[$i * 24 + $j];
		if ($day[$i] > 0)
		{
			$dayMin = min($dayMin, $day[$i]);
			$dayMax = max($dayMax, $day[$i]);
		}
	}
	$dayMax = max(1, $dayMax, $dayAve);
	if ($dayAve > 0)
	{
		$dayMin = min($dayMin, $dayAve);
	}
	if ($totalAve > 0)
	{
		$dayMin = min($dayMin, $totalAve);
	}
	$now = time();
	$p_day = array();
	for ($i=0; $i<31; $i++)
	{
		if ($i < 28)
		{
			$p_day[$now+($i-28)*86400] = $day[$i];
		}
		else if ($i == 28)
		{
			$p_day['今日'] = $day[28];
		}
		else if ($i == 29)
		{
			$p_day['28日平均'] = $dayAve;
		}
		else if ($i == 30)
		{
			$p_day['累計平均'] = $totalAve;
		}
	}
	return array($p_day, $dayMax, $dayMin);
}

function order_t12m($month)
{
	$monthMax = $monthMin = max(max($month), 1);
	$monthAve = $cnt = 0;
	foreach ($month as $v)
	{
		if ($v > 0)
		{
			$monthAve += $v;
			$monthMin = min($monthMin, $v);
			$cnt++;
		}
	}
	$monthAve /= max($cnt, 1);
	$monthName = explode(' ', 'Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec');
	$thismonth = date('n');
	$p_month = array();
	for ($i=0; $i<12; $i++)
	{
		$n = ($thismonth + 11 + $i) % 12;
		$p_month[$monthName[$n]] = $month[$n];
	}
	$p_month['今月'] = $month[12];
	return array($p_month, $monthMax, $monthMin, $monthAve);
}

function order_week($epocs, $hours, $span, $dayAve)
{
	$day = array_fill(0, 29, 0);
	for ($i=0; $i<29; $i++)
	{
		for ($j=0; $j<24; $j++) $day[$i] += $hours[$i * 24 + $j];
	}
	$week = $cnt = array_fill(0, 7, 0);
	for ($i=0; $i<28; $i++)
	{
		$n = ($i + date('w') + 7) % 7;
		$week[$n] += $day[$i];
		if ($day[$i]>0 || $cnt[$n]>0)
		{
			$cnt[$n]++;
		}
	}
	$weekName = explode(' ', 'Sun Mon Tue Wed Thu Fri Sat');
	$p_week = array();
	foreach ($week as $i => $v)
	{
		$p_week[$weekName[$i]] = round($v / max($cnt[$i], 1));
	}
	$weekMax = 1;
	$weekMin = round($dayAve);
	foreach ($p_week as $v)
	{
		if ($v > 0)
		{
			$weekMax = max($weekMax, $v);
			$weekMin = min($weekMin, $v);
		}
	}
	return array($p_week, $weekMax, $weekMin);
}

function order_vcnt($vcnt)
{
	extract($GLOBALS);
	$p_vcnt = array();
	foreach ($ARRAY_VCNT as $i => $v)
	{
		$p_vcnt[$v . ' 回'] = $vcnt[$i];
	}
	$vcntMax = max(max($vcnt), 1);
	return array($p_vcnt, $vcntMax);
}

function order_vspn($vspn)
{
	extract($GLOBALS);
	$p_vspn = array();
	foreach ($ARRAY_VSPN as $i => $v)
	{
		$v = $v<24 ? $v . ' 時間' : ($v<168 ? $v/24 . ' 日' : $v/168 . ' 週間');
		$p_vspn[$v . '以内'] = $vspn[$i];
	}
	$vspnMax = max(max(array_values($vspn)), 1);
	return array($p_vspn, $vspnMax);
}

function order_url($lines)
{
	extract($GLOBALS);
	$hash = array();
	foreach ($lines as $line)
	{
		if ($line)
		{
			$hash[$line] = isset($hash[$line]) ? $hash[$line]+1 : 1;
		}
	}
	$urls = array();
	if (count($PFXS) && count($hash))
	{
		$pattern = '^https?://(' . implode('|', $PFXS) . ')';
		foreach ($hash as $url => $val)
		{
			if (ereg($pattern, $url, $r))
			{
				$url = str_replace($r[0], null, $url);
				if ($url == null)
				{
					$url = '/';
				}
				$urls[$url] = isset($urls[$url]) ? $urls[$url]+$val : $val;
			}
			else
			{
				$urls[$url] = $val;
			}
		}
	}
	else
	{
		$urls = $hash;
	}
	return $urls;
}

function order_lang($hash)
{
	arsort($hash);
	$iso639 = array(
		'aa'=>'アファル語','ab'=>'アプハジア語','af'=>'アフリカーンス語',
		'am'=>'アムハラ語','ar'=>'アラビア語','as'=>'アッサム語','ay'=>'アイマラ語',
		'az'=>'アゼルバイジェン語','ba'=>'バシキール語','be'=>'白ロシア語',
		'bg'=>'ブルガリア語','bh'=>'ビハール語','bi'=>'ビスラマ語',
		'bn'=>'ベンガル語','bo'=>'チベット語','br'=>'ブルターニュ語',
		'ca'=>'カタラン語','co'=>'コルシカ語','cs'=>'チェコスロバキア語',
		'cy'=>'ウェールズ語','da'=>'デンマーク語','de'=>'ドイツ語',
		'dz'=>'ブータン語','el'=>'ギリシャ語','en'=>'英語','eo'=>'エスペラント語',
		'es'=>'スペイン語','et'=>'エストニア語','eu'=>'バスク語','fa'=>'ペルシャ語',
		'fi'=>'フィンランド語','fj'=>'フィジー語','fo'=>'フェロー語',
		'fr'=>'フランス語','fy'=>'フリジア語','ga'=>'アイルランド語',
		'gd'=>'スコットランドゲーリック語','gl'=>'ガリシア語','gn'=>'グアラニー語',
		'gu'=>'グジャラト語','ha'=>'ハウサ語','he'=>'ヘブライ語','iw'=>'ヘブライ語',
		'hi'=>'ヒンディー語','hr'=>'クロアチア語','hu'=>'ハンガリー語',
		'hy'=>'アルメニア語','ia'=>'インターリンガ(国際語)','id'=>'インドネシア語',
		'in'=>'インドネシア語','ie'=>'インターリング','ik'=>'イヌピア語',
		'is'=>'アイスランド語','it'=>'イタリア語','ja'=>'日本語','jw'=>'ジャワ語',
		'ka'=>'ジョージア語','kk'=>'カザフ語','kl'=>'グリーンランド語',
		'km'=>'カンボジア語','kn'=>'カンナダ語','ko'=>'韓国語','ks'=>'カシミール語',
		'ku'=>'クルド語','ky'=>'キルギス語','la'=>'ラテン語','ln'=>'リンガラ語',
		'lo'=>'ラオタ語','lt'=>'リトアニア語','lv'=>'ラトビアレット語',
		'mg'=>'マダガスカル語','mi'=>'マオリ語','mk'=>'マカドニア語',
		'ml'=>'マラヤーラム語','mn'=>'モンゴル語','mo'=>'モルダビア語',
		'mr'=>'マラッタ語','ms'=>'マレー語','mt'=>'マルタ語','my'=>'ビルマ語',
		'na'=>'ナウル語','ne'=>'ネパール語','nl'=>'オランダ語','no'=>'ノルウェー語',
		'oc'=>'オキタン語','om'=>'オロモ語','or'=>'オーリア語','pa'=>'パンジャビ語',
		'pl'=>'ポーランド語','ps'=>'パシト語','pt'=>'ポルトガル語',
		'qu'=>'クエチュア語','rm'=>'レトロアン(ス)語','rn'=>'キルンディ語',
		'ro'=>'ルーマニア語','ru'=>'ロシア語','rw'=>'キヤーワンダ語',
		'sa'=>'サンスクリット語','sd'=>'シンド語','sg'=>'サングホ語',
		'sh'=>'セルボクロアチア語','si'=>'シンハラ語','sk'=>'スルバキア語',
		'sl'=>'スロベンニア語','sm'=>'サモア語','sn'=>'ショナ語','so'=>'マリ語',
		'sq'=>'アルバニア語','sr'=>'セルビア語','ss'=>'シスワティ語','st'=>'セト語',
		'su'=>'スーダン語','sv'=>'スウェーデン語','sw'=>'スワヒリ語',
		'ta'=>'タミル語','te'=>'テルグ語','tg'=>'タジク語','th'=>'タイ語',
		'ti'=>'チグリニャ語','tk'=>'トルクメン語','tl'=>'タガログ語',
		'tn'=>'セツワナ語','to'=>'トンガ語','tr'=>'トルコ語','ts'=>'ヅォンガ語',
		'tt'=>'タタール語','tw'=>'トウィ語','uk'=>'ウクライナ語',
		'ur'=>'ウルドゥー語','uz'=>'ウズベク語','vi'=>'ベトナム語',
		'vo'=>'ヴォラピュック語','wo'=>'ウォロフ語','xh'=>'コーサ語',
		'yi'=>'イディッシュ語','ji'=>'イディッシュ語','yo'=>'ヨルバ語',
		'zh'=>'中国語','zu'=>'ズールー語',
	);
	$langs = array();
	foreach ($hash as $k => $v)
	{
		if (isset($iso639[$k]))
		{
			$langs["<code>$k</code> " . $iso639[$k]] = $v;
		}
	}
	return $langs;
}

function format_data($lines)
{
	$epocs = isset($lines[0]) && $lines[0]>1 ? $lines[0] : time();
	$epocp = isset($lines[1]) && $lines[1]>1 ? $lines[1] : time();
	$total = isset($lines[2]) ? $lines[2] : 0;
	$month = isset($lines[3]) ? explode(',', $lines[3]) : array();
	$hours = isset($lines[4]) ? explode(',', $lines[4]) : array();
	while (count($month) > 13) array_pop($month);
	while (count($month) < 13) array_unshift($month, 0);
	while (count($hours) > 696) array_pop($hours);
	while (count($hours) < 696) array_unshift($hours, 0);
	$pre = getdate($epocp);
	$now = getdate();
	if ($pre['mon'] != $now['mon'])
	{
		$month[$pre['mon'] - 1] = $month[12];
		$month[12] = 0;
	}
	if ($pre['mday']!=$now['mday'] || $pre['mon']!=$now['mon'])
	{
		$days = intval(((time()-$epocp) + ($epocp+date('Z'))%86400) / 86400);
		for ($i=0; $i<24*$days; $i++)
		{
			array_shift($hours);
			array_push($hours, 0);
		}
	}
	$zone = date('Z');
	$span = intval((time()+$zone)/86400) - intval(($epocs+$zone)/86400);
	$span = max(1, $span);
	return array($epocs, $epocp, $total, $month, $hours, $span);
}

function update_data($user)
{
	global $BASE;
	$file_name = "$BASE.data/$user/data.txt";
	if (! is_file($file_name))
	{
		touch($file_name);
	}
	$lines = array();
	$fp = fopen($file_name, 'r+');
	flock($fp, LOCK_EX);
	while ($line = fgets($fp)) array_push($lines, trim($line));
	list($epocs, $epocp, $total, $month, $hours, $span) = format_data($lines);
	$epocp = time();
	$total++;
	$month[12]++;
	$month = implode(',', $month);
	$hours[672 + date('H')]++;
	$hours = implode(',', $hours);
	$data = "$epocs\n$epocp\n$total\n$month\n$hours";
	fseek($fp, 0);
	ftruncate($fp, 0);
	fwrite($fp, $data);
	fclose($fp);
	return;
}

function format_vcnt($lines)
{
	extract($GLOBALS);
	$rev = isset($lines[0]) && $lines[0]>1 ? $lines[0] : null;
	$vcnts = isset($lines[1]) ? explode(',', $lines[1]) : array();
	while (count($vcnts) > count($ARRAY_VCNT)) array_pop($vcnts);
	while (count($vcnts) < count($ARRAY_VCNT)) array_push($vcnts, 0);
	return array($rev, $vcnts);
}

function update_vcnt($c_rev, $c_count, $now)
{
	extract($GLOBALS);
	$file_name = "$BASE.data/$U/vcnt.txt";
	if (! is_file($file_name) || filesize($file_name)==0)
	{
		$fp = fopen($file_name, 'w');
		flock($fp, LOCK_EX);
		fwrite($fp, time() . "\n");
		fclose($fp);
	}
	$lines = array();
	$fp = fopen($file_name, 'r+');
	flock($fp, LOCK_EX);
	while ($line = fgets($fp)) array_push($lines, trim($line));
	list($rev, $vcnts) = format_vcnt($lines);
	$c_count = ($c_rev==$rev) ? $c_count+1 : 1;
	$index = -1;
	foreach ($ARRAY_VCNT as $i => $v)
	{
		if ($c_count < $v)
		{
			break;
		}
		if ($c_count == $v)
		{
			$index = $i;
			break;
		}
	}
	if ($index != -1)
	{
		$vcnts[$index]++;
		if ($index!=0 && $vcnts[$index-1]>1)
		{
			$vcnts[$index-1]--;
		}
		$data = "$rev\n" . implode(',', $vcnts);
		fseek($fp, 0);
		ftruncate($fp, 0);
		fwrite($fp, $data);
	}
	fclose($fp);
	setcookie($U, "$rev,$c_count,$now", $now + $COOKIE,
		$SCRIPT_DIR, $_SERVER['HTTP_HOST']);
	return $c_count;
}

function format_view($lines)
{
	$epocp = isset($lines[0]) && $lines[0]>1 ? $lines[0] : time();
	$views = isset($lines[1]) ? explode(',', $lines[1]) : array();
	while (count($views) > 192) array_pop($views);
	while (count($views) < 192) array_unshift($views, 0);
	$pre = getdate($epocp);
	$now = getdate();
	if ($pre['mday']!=$now['mday'] || $pre['mon']!=$now['mon'])
	{
		$days = intval((((time()-$epocp) + ($epocp+date('Z'))%86400) / 86400));
		for ($i=0; $i<24*$days; $i++)
		{
			array_shift($views);
			array_push($views, 0);
		}
	}
	return array($epocp, $views);
}

function update_view($user)
{
	global $BASE;
	$file_name = "$BASE.data/$user/view.txt";
	if (! is_file($file_name))
	{
		touch($file_name);
	}
	$lines = array();
	$fp = fopen($file_name, 'r+');
	flock($fp, LOCK_EX);
	while ($line = fgets($fp)) array_push($lines, trim($line));
	list($epocp, $views) = format_view($lines);
	$views[168 + date('h')]++;
	$data = time() . "\n" . implode(',', $views);
	fseek($fp, 0);
	ftruncate($fp, 0);
	fwrite($fp, $data);
	fclose($fp);
	return;
}

function read_format_srch()
{
	extract($GLOBALS);
	$lines = io_read($U, 'srch.txt');
	$engns = $phrss = $words = array();
	foreach ($lines as $line)
	{
		if (! $line)
		{
			continue;
		}
		list($egnl, $egns, $phrs) = explode('<>', $line);
		$engn = "$egns $egnl";
		$engns[$engn] = isset($engns[$engn]) ? $engns[$engn]+1 : 1;
		$phrss[$phrs] = isset($phrss[$phrs]) ? $phrss[$phrs]+1 : 1;
		foreach (explode(' ', str_replace('"', null, $phrs)) as $word)
		{
			$words[$word] = isset($words[$word]) ? $words[$word]+1 : 1;
		}
	}
	return array($engns, $phrss, $words);
}

function read_format_hist()
{
	extract($GLOBALS);
	$lines = io_read($U, 'hist.txt');
	$domain = array();
	$domain[] = '\.[-_0-9a-z]{2,}\.(ne|or|co|ac|ad|go|ed|gr|lg)\.[a-z]{2}$';
	$domain[] = '\.[-_0-9a-z]{2,}\.(com|net|org|edu|gov|mil)(\.[a-z]{2})?$';
	$domain[] = '\.[-_0-9a-z]{2,}\.[a-z]{2,}$';
	$domain = implode('|', $domain);
	$hosts = $agnts = $osyss = $scrss = $scrds = $langs = array();
	$vspns = array_fill(0, count($ARRAY_VSPN), 0);
	$java = 0;
	foreach ($lines as $line)
	{
		if (! $line)
		{
			continue;
		}
		$hists = explode('<>', $line);
		$addr = isset($hists[1]) ? $hists[1] : null;
		$host = isset($hists[3]) ? $hists[3] : null;
		$agnt = isset($hists[6]) ? $hists[6] : null;
		$scrn = isset($hists[7]) ? $hists[7] : null;
		$vspn = isset($hists[9]) ? $hists[9] : null;
		$lang = isset($hists[10]) ? $hists[10] : null;
		if (! $addr && isset($hists[2]))
		{
			$addr = $hists[2];
		}
		if (! $host && isset($hists[4]))
		{
			$host = $hists[4];
		}
		$x = strtolower($host);
		if (ereg($domain, $x, $r))
		{
			$x = '*' . $r[0];
		}
		else if ($x == null)
		{
			$x = '?';
		}
		$hosts[$x] = isset($hosts[$x]) ? $hosts[$x]+1 : 1;
		$x = $agnt;
		$y = null;
		if (ereg('Windows NT 5\.1', $x))
		{
			if (ereg('Media Center PC ([0-9])', $x, $r))
			{
				$x = 'Windows XP&#32;MCE&#32;' . ($r[1]<3 ? '2004' : '2005');
			}
			else
			{
				$x = 'Windows XP';
			}
		}
		else if (ereg('Windows (NT 5\.0|2000)', $x))
		{
			$x = 'Windows 2000';
		}
		else if (ereg('Windows NT 5\.2', $x))
		{
			$x = 'Windows Server&#32;2003';
		}
		else if (ereg('Windows ME|Win 9x', $x))
		{
			$x = 'Windows Me';
		}
		else if (ereg('Windows 98|Win98', $x))
		{
			$x = 'Windows 98';
		}
		else if (ereg('Windows 95|Win95', $x))
		{
			$x = 'Windows 95';
		}
		else if (ereg('Windows CE', $x))
		{
			$x = 'Windows CE';
		}
		else if (ereg('Windows NT|WinNT', $x))
		{
			$x = 'Windows NT';
		}
		else if (ereg('Windows [a-zA-Z0-9 \.]+', $x, $r))
		{
			$x = $r[0];
		}
		else if (ereg('Mac OS X', $x))
		{
			$x = 'Mac&#32;OS&#32;X';
		}
		else if (ereg('Mac_PowerPC|Macintosh', $x))
		{
			$x = 'Mac&#32;OS';
		}
		else if (eregi('Linux|FreeBSD|NetBSD|SunOS|HP-UX|IRIX|AIX|OSF1', $x, $r))
		{
			$x =  'UNIX ' . $r[0];
		}
		else if (ereg('UP\.Browser/([0-9]\.[0-9]+)', $x, $r))
		{
			$x = 'EZweb';
			$y = 'UP.Browser ' . $r[1];
		}
		else if (ereg('DoCoMo/([0-9]\.[0-9]+)', $x, $r))
		{
			$x = 'DoCoMo';
			$y = 'DoCoMo ' . $r[1];
		}
		else if (ereg('^J-PHONE/([0-9]\.[0-9]+)', $x, $r))
		{
			$x = 'Vodafone';
			$y = 'J-PHONE ' . $r[1];
		}
		else if (ereg('^Vodafone/([0-9]\.[0-9]+)', $x, $r))
		{
			$x = 'Vodafone';
			$y = 'Vodafone ' . $r[1];
		}
		else if (ereg('PalmOS', $x))
		{
			$x = 'PalmOS';
		}
		else if (ereg('^DDIPOCKET', $x))
		{
			$x = 'AirH&quot;';
		}
		else if (ereg('BeOS', $x))
		{
			$x = 'BeOS';
		}
		else
		{
			$x = 'unknown';
		}
		if (! $y)
		{
			$y = $agnt;
			if (ereg('Opera[/ ]([0-9]+(\.[0-9])?)', $y, $r))
			{
				$y = 'Opera ' . $r[1];
			}
			else if (ereg('MSIE ([0-9]+(\.[0-9])?)', $y, $r))
			{
				$y = 'Internet Explorer ' . $r[1];
			}
			else if (eregi(' ([a-z][a-z0-9\.\-]+)/([0-9]+(\.[0-9]+)?)\+?$', $y, $r))
			{
				$y = ($r[1]=='Netscape6' ? 'Netscape' : $r[1]) . ' ' . $r[2];
			}
			else if (ereg('jig browser [0-9]+(\.[0-9]+)?', $y, $r))
			{
				$x = ereg(' [A-Z]{2}[0-9]{2}\)', $y) ? 'EZweb' : 'DoCoMo';
				$y = $r[0];
			}
			else if (ereg('^NetPositive/([0-9]+(\.[0-9]+)?)', $y, $r))
			{
				$y = $r[1] . ' ' . $r[2];
			}
			else if (eregi('^([a-z][ a-z0-9\.\-]+)/([0-9]+(\.[0-9]+)?)', $y, $r))
			{
				$y = $r[1] . ' ' . $r[2];
			}
			else if (! $y)
			{
				$y = 'unknown';
			}
		}
		$osyss[$x] = isset($osyss[$x]) ? $osyss[$x]+1 : 1;
		$agnts[$y] = isset($agnts[$y]) ? $agnts[$y]+1 : 1;
		if (ereg('([0-9]{3,4}x[0-9]{3,4})x([0-9]{1,2})', $scrn, $r))
		{
			$scrss[$r[1]] = isset($scrss[$r[1]]) ? $scrss[$r[1]]+1 : 1;
			$bit =  $r[2] . ' bit';
			$scrds[$bit] = isset($scrds[$bit]) ? $scrds[$bit]+1 : 1;
			$java++;
		}
		if ($vspn)
		{
			$vspn /= 3600;
			foreach ($ARRAY_VSPN as $i => $v)
			{
				if ($vspn <= $v)
				{
					$vspns[$i] = isset($vspns[$i]) ? $vspns[$i]+1 : 1;
					break;
				}
			}
		}
		if ($lang)
		{
			if (strlen($lang) > 2)
			{
				$lang = substr($lang, 0, 2);
			}
			$langs[$lang] = isset($langs[$lang]) ? $langs[$lang]+1 : 1;
		}
	}
	$java /= max(count($lines), 1);
	return array($hosts, $agnts, $osyss, $scrss, $scrds, $vspns, $java, $langs);
}

function read_cookie()
{
	extract($GLOBALS);
	$c_rev = $c_count = $c_last = null;
	if (isset($_COOKIE[$U]))
	{
		list($c_rev, $c_count, $c_last) = explode(',', $_COOKIE[$U]);
	}
	return array($c_rev, $c_count, $c_last);
}

function read_cookie_id()
{
	extract($GLOBALS);
	$c_id = null;
	if (isset($_COOKIE['__id']) && strlen($_COOKIE['__id'])==8)
	{
		$c_id = $_COOKIE['__id'];
	}
	else
	{
		$c_id = base64_encode(pack('H*', md5(time() . $_SERVER['REMOTE_ADDR'])));
		$c_id = substr(str_replace('+', null, str_replace('/', null, $c_id)), 0, 8);
		setcookie('__id', $c_id, time() + $COOKIE,
			null, $SCRIPT_DIR, $_SERVER['HTTP_HOST']);
	}
	return $c_id;
}

function io_read($user, $name)
{
	global $BASE;
	$file_name = "$BASE.data/$user/$name";
	$lines = array();
	if (is_file($file_name))
	{
		$fp = fopen($file_name, 'r');
		flock($fp, LOCK_EX);
		while ($line = fgets($fp, 4096)) array_push($lines, trim($line));
		fclose($fp);
	}
	return $lines;
}

function io_rotate($user, $name, $data, $logmax)
{
	global $BASE;
	$lines = array($data . "\n");
	$file_name = "$BASE.data/$user/$name";
	if (! is_file($file_name))
	{
		touch($file_name);
	}
	$fp = fopen($file_name, 'r+');
	flock($fp, LOCK_EX);
	while ($line = fgets($fp, 2048)) array_push($lines, $line);
	while (count($lines) > $logmax-1) array_pop($lines);
	fseek($fp, 0);
	ftruncate($fp, 0);
	fwrite($fp, implode(null, $lines));
	fclose($fp);
	return;
}

function create_image($user, $color1, $color2)
{
	extract($GLOBALS);
	$plte = pack('H*', "504c5445ffffff$color1$color2");
	$plte = bin2hex($plte) . str_pad(dechex(crc32($plte)), 8, '0', STR_PAD_LEFT);
	$img = null;
	$img .= '89504E470D0A1A0A0000000D49484452000001380000001F0403000000818FCC';
	$img .= '6300000009' . $plte . '0000000174524E530040';
	$img .= 'E6D866000002574944415478DACD980176C32008862127809E20C9FD0F390515';
	$img .= '22F6C5BCD5ADAECF26EE977E4145090000B215825C5C034BC32AC97D79D9254A';
	$img .= '1FDCAD85574AEE0B6E764DD287CC2CE24AC97D21EB8340529B59C27592E43D6E';
	$img .= '7FD9BBD280F9519890B27311AC0F895932B34909CB2488F24DB503E5061238A8';
	$img .= '5FD647EE72BD9B1184751285AB9E3338715FBE4BBACD8C94CF6E46F4B3449220';
	$img .= 'D46BEA59261D4FA86DD4AF77EAD73BC22A89CC2D37E7AAE7C49B249E237B206C';
	$img .= '23B0DB13EA08AC904C94DC471FD39B2D4F6C66A344626A9488A3BC1571D44832';
	$img .= '09C7FD2F1F018E07704389833B02DC45522247329186308D2C0DE130FC32F770';
	$img .= '3880C3A1C4C1710F871D1C9679266103C79E7B9DA91CCE2CE686D39B0D920497';
	$img .= 'DA3C9C484E69F35672B7B1E4CBE1B80EEBDFC1C13D5C0B252495CCB9F770CCAF';
	$img .= 'EB332790CEEC553284F3716E6485F5B6C6B9A9D52ABF12E0B087A37B38F2712E';
	$img .= '5AD9B4ED519CFB2F38D945654B603D82C83E4B11EE33C3FA180EDBA685750E2E';
	$img .= '9B73BF86E3077014E060011C69F5D673A308D5CE13D3A1E4BA4344FFF770A491';
	$img .= '0ECA41E99DE786707D10FB34DCEC6A559A74E1E1CE33351C57B82A01F967BAF5';
	$img .= '128573560A5C956CE721B7A93E9F849232E7CAA155CCB613ED48B281798E7AFF';
	$img .= '372B6D87A847E3E6399C61CB277B17C474900EB5E80EFF41B21B9C49AEB3E130';
	$img .= 'B8964234B8B9CC302549B20AA52A4912EA427579549080C25D2565E3342B0AE7';
	$img .= 'B231B58C938ECBC9A44C62D9154BBE89F5FD41CB378304CA59F322297BABB3A2';
	$img .= '2BC4B2562C8789D9947A6F976BB3FB209928DFFCA2E4EF5E2805C97DF9015164';
	$img .= '977DCEA4F6880000000049454E44AE426082';
	$img = pack('H*', $img);
	$fp = fopen("$BASE.data/$user/logo.png", 'w');
	flock($fp, LOCK_EX);
	fwrite($fp, $img);
	fclose($fp);
	$img = null;
	$img .= '89504E470D0A1A0A0000000D4948445200000011000000110403000000C9435A';
	$img .= 'C900000009' . $plte . '0000000174524E530040';
	$img .= 'E6D8660000003E4944415478DAA5CEC10D00200843D15F27A84E60D87F488183';
	$img .= 'F16EB9BC109202CC8E419B8C526ED98892F0BDAAA9DDBF22B32235BA4C4F2DAB';
	$img .= 'C0E4F9EE00E72D05C996E6E0D80000000049454E44AE426082';
	$img = pack('H*', $img);
	$fp = fopen("$BASE.data/$user/cube.png", 'w');
	flock($fp, LOCK_EX);
	fwrite($fp, $img);
	fclose($fp);
	$img = null;
	$img .= '89504E470D0A1A0A0000000D494844520000001F0000001F0403000000ED800B';
	$img .= '0A00000009' . $plte . '0000000174524E530040';
	$img .= 'E6D8660000007A4944415478DA9591010A80300845BF9E407782EAFE874C5DA5';
	$img .= '48D09221FA989BFA0180344D2CC7C063E4803881389004047197401C4802F2CC';
	$img .= '1CE7053BE54BBD8B382B2E303B2B401BA00EC661B6CF41D681AABB0AE413E86F';
	$img .= '10B6002CE86FC412B9F411459BC7716D2E801163C54642A62D65A23761BB0C38';
	$img .= '01BAD816BC4CBEB1530000000049454E44AE426082';
	$img = pack('H*', $img);
	$fp = fopen("$BASE.data/$user/mini.png", 'w');
	flock($fp, LOCK_EX);
	fwrite($fp, $img);
	fclose($fp);
	$plte = "ffffff$color1$color2";
	$img = null;
	$img .= '4749463839611F001F00830000' . $plte . '00000000000000000000';
	$img .= '000000000000000000000000000000000000000000000000000000000021F904';
	$img .= '03000000002C000000001F001F0000049E10C80982BD38EB4B65105D284E5617';
	$img .= '00E0A87A1575A2ABFABEAD94C626C94E37BEB3349B4FE7DA188FA152A7170B7A';
	$img .= '9CB0A3B2560C09A453E89484A5699D1F81783C369AC0E4F4757336A9C9E6AA2B';
	$img .= '9DA16F2B6878295CBEFBED277C621C72248016806D737A1789851E87826B6079';
	$img .= '6F6A845C6E96977E959B7D927F8C888C2E5428A17B8E353420A9907A4435296C';
	$img .= '4F1A3F2D4C432D68BB495F5D522211003B';
	$img = pack('H*', $img);
	$fp = fopen("$BASE.data/$user/mini.gif", 'w');
	flock($fp, LOCK_EX);
	fwrite($fp, $img);
	fclose($fp);
	return;
}

function tag_pc($user)
{
	extract($GLOBALS);
	$tag =<<< EOD
<script type="text/javascript"><!--
document.write('<a target="_blank" '
+'href="$URL?u=$user"'
+'><img src="$URL'
+'?u=$user&m=c&refr='+escape(top.document.referrer)
+'&scrn='+screen.width+'x'+screen.height+'x'+screen.colorDepth
+'&navi='+navigator.userAgent+'" style="width:31px;height:31px;border:0px;"'
+' /></a>');//--></script><noscript><a target="_blank"
href="$URL?u=$user"><img
src="$URL?u=$user&m=c"
style="width:31px;height:31px;border:0px;" /></a></noscript>
EOD;
	return $tag;
}

function tag_cp($user)
{
	extract($GLOBALS);
	$tag =<<< EOD
<img src="$URL?u=$user&m=c"
style="width:31px;height:31px;border:0px;" border="0px" />\n
EOD;
	return $tag;
}

function gsort($hash, $sep=' ')
{
	$orgn = $hash;
	arsort($orgn);
	$grps = array();
	foreach ($orgn as $k => $v)
	{
		if (ereg("^(.+)$sep(.+)$", $k, $r))
		{
			$grps[$r[1]] = isset($grps[$r[1]]) ? $grps[$r[1]]+$v : $v;
		}
		else
		{
			$grps[$k] = $v;
		}
	}
	arsort($grps);
	$hash = array();
	foreach ($grps as $grp => $grp_v)
	{
		$hash[$grp] = $grp_v;
		foreach ($orgn as $k => $v)
		{
			$grp_meta = quotemeta($grp);
			if (ereg("^$grp_meta (.*)?", $k, $r))
			{
				if ($r[1])
				{
					$hash["<!--$grp-->&nbsp; - " . $r[1]] = $v;
				}
				else
				{
					$hash[$k] = $v;
				}
			}
		}
	}
	return $hash;
}
function get_param($str, $default_value=null)
{
	if (isset($_POST[$str]) && strlen($_POST[$str]))
	{
		$str = $_POST[$str];
	}
	else if (isset($_GET[$str]) && strlen($_GET[$str]))
	{
		$str = $_GET[$str];
	}
	else if (isset($_COOKIE[$str]) && strlen($_COOKIE))
	{
		$str = $_COOKIE[$str];
	}
	else
	{
		$str = $default_value;
	}
	return $str;
}

function info($title, $message, $debug=false)
{
	extract($GLOBALS);
	exits(uipc_html_layer3($title, $message), $debug);
}

function memo($capt, $data)
{
	extract($GLOBALS);
	$proc = sprintf('%0.4f', array_sum(explode(' ', microtime())) - $START);
	$data = "$capt\n$proc\n" . serialize($data) . "\n\n";
	$fp = fopen('debug.txt', 'a');
	flock($fp, LOCK_EX);
	fwrite($fp, $data);
	fclose($fp);
	return;
}

function exits($html, $debug=false)
{
	extract($GLOBALS);
	if ($M=='c' && ! $debug)
	{
		if ($U==$ADMIN && isset($_GET['uu']))
		{
			$U = $_GET['uu'];
		}
		$ext = $GLOBALS['USE_GIF'] ? 'gif' : 'png';
		header("Content-Type: image/$ext");
		readfile("$BASE.data/$U/mini.$ext");
		//{
		//}
		exit;
	}
	$target = '%TIME%';
	$offset = strpos($html, $target);
	if ($offset != 0)
	{
		$length = strlen($target);
		$proc = sprintf('%0.4f', array_sum(explode(' ', microtime())) - $START);
		$html = substr_replace($html, $proc, $offset, $length);
	}
	echo $html;
	exit;
}

function uipc_html_layer1($html)
{
	extract($GLOBALS);
	$title = "アクセス解析 apricot.php" . (($U && $U!=$ADMIN) ? " - $U" : null);
	$upper = strtoupper($WEBSITE);
	$bar_height = '8px';
	if (isset($_SERVER['HTTP_USER_AGENT']))
	{
		if (ereg('Gecko', $_SERVER['HTTP_USER_AGENT']))
		{
			$bar_height = '6px';
		}
	}
	$html = rtrim($html);
	$html =<<< EOD
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<!-- GENERATED BY $upper (%TIME% sec) -->
<html lang="ja-JP">
<head>
	<meta http-equiv="Cache-Control" content="no-cache" />
	<meta http-equiv="Content-Type" content="text/html; charset=shift_jis" />
	<meta http-equiv="Content-Style-Type" content="text/css" />
	<meta http-equiv="Content-Script-Type" content="text/javascript" />
	<title>$title $SCRIPT_DIR</title>
	<style type="text/css">
	a {
		color:#0000FF;
		text-decoration:none;
	}
	a:visited {
		color:darkviolet;
		text-decoration:none;
	}
	a:hover {
		color:#FF0000;
		position:relative;
		left:-1px;
		top:-1px;
	}
	body, td {
		font:normal normal normal 9pt/10pt "MS UI Gothic";
		color:#333333;
		background-color:#FFFFFF;
	}
	body {
		margin:0px;
		padding:0px;
	}
	td {
		white-space:nowrap;
		padding:1px;
	}
	table {
		border:0px;
		border-spacing:0px;
		margin:auto;
	}
	table.outer {
		width:624;
		margin:0px 8px 4px 8px;
	}
	td.outerCell {
		vertical-align:top;
		padding:0px 8px 4px 8px;
	}
	div.subtitle {
		border-bottom:1px solid #333333;
		letter-spacing:3pt;
		padding:0px 0px 3px 0px;
		margin:0px 16px 12px 16px;
	}
	div.unitHead {
		border-bottom:1px solid #333333;
		letter-spacing:3pt;
		text-align:left;
		padding:0px 0px 3px 0px;
	}
	div.unitBody {
		text-align:center;
		padding:8px 0px 8px 0px;
	}
	.count {
		color:#$COL1;
		text-align:right;
		vertical-align:top;
		padding:1px 2px 0px 6px;
	}
	.barBg {
		background-color:#E7E7E7;
		padding:2px 0px 2px 0px;
	}
	.barBgAve {
		background-color:#D7D7D7;
		padding:2px 0px 2px 0px;
	}
	.bar {
		background-color:#$COL2;
		border-top:1px solid #FFFFFF;
		border-right:1px solid #$COL1;
		border-bottom:1px solid #$COL1;
		height:$bar_height;
	}
	.barCur {
		background-color:#7F7FFF;
		border-top:1px solid #FFFFFF;
		border-right:1px solid #0000FF;
		border-bottom:1px solid #0000FF;
		height:$bar_height;
	}
	.barAve {
		background-color:#D7D7D7;
		height:$bar_height;
		margin-bottom:1px;
	}
	.cur {
		color:#0000FF;
	}
	.str {
		color:#$COL1;
	}
	.red {
		color:#FF0000;
	}
	.sta {
		color:#333333;
		text-decoration:none;
	}
	div.block {
		white-space:normal;
		margin:2px 2px 12px 40px;
	}
	.top {
		vertical-align:top;
	}
	.right {
		text-align:right;
	}
	img.cube {
		border:0px;
		width:17px;
		height:17px;
		margin:0px 6px -2px 0px;
	}
	div.line1 {
		background-color:#$COL1;
		width:100%;
		height:9px;
		margin-bottom:2px;
	}
	div.line2 {
		background-color:#$COL2;
		width:100%;
		height:3px;
		margin-bottom:16px;
	}
	</style>
</head>
<body>
<div style="width:640px;">

$html

</div>
</body>
</html>
EOD;
	return $html;
}

function uipc_html_layer2($html, $jump=null)
{
	extract($GLOBALS);
	$username = ($U && $U!=$ADMIN) ? "user : <code>$U</code>" : "ver. $VERSION";
	if (! $jump)
	{
		$item = '<span class="cur">&lt;&lt; 戻る</span>';
		$jump = "<a href=\"$BASE?u=$U\">$item</a>";
	}
	$html = rtrim($html);
	$html =<<< EOD
<table cellspacing="0" style="width:100%;">
<tr>
	<td style="padding:16px 0px 10px 12px;" rowspan="2">
		<a href="http://$WEBSITE"
			target="_blank"><img src="$BASE?u=$U&m=logo" alt="apricot"
			style="width:312px;height:31px;border:0px;" /></a></td>
	<td style="padding:8px 8px 0px 0px;text-align:right;vertical-align:top;">
		$username</td>
</tr>
<tr>
	<td style="padding:0px 8px 4px 0px;text-align:right;vertical-align:bottom;">
$jump
	</td>
</tr>
</table>
<div class="line1"><!----></div>
<div class="line2"><!----></div>

$html
EOD;
	return uipc_html_layer1($html);
}

function uipc_html_layer3($title, $html, $jump=null)
{
	extract($GLOBALS);
	$html = rtrim($html);
	$html =<<< EOD
<div class="subtitle">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$title</div>
<div style="margin:8px 0px 8px 32px;">
$html

</div>
EOD;
	return uipc_html_layer2($html, $jump);
}

function uipc_auth()
{
	extract($GLOBALS);
	$html =<<< EOD
<div style="margin:32px 0px 0px 32px;">
	<table cellspacing="0" style="margin:0px auto 0px 0px;">
	<tr>
		<td><a href="http://$WEBSITE"><img src="$BASE?u=$U&m=mini"
			alt="apricot" style="border:0px;" /></a></td>
		<td style="padding:6px 0px 0px 12px;">
			アクセス解析
			<div style="width:0px;height:1px;"><!----></div>
			<a href="http://$WEBSITE"><code
				style="color:#333333;">$WEBSITE</code></a></td>
	</tr>
	</table>
</div>
<div style="margin:16px 0px 0px 36px;">
	<span class="str">パスワード</span><br />
	<form action="$BASE" method="post" style="margin:0px;">
	<input type="hidden" name="u" value="$U" />
	<input type="hidden" name="m" value="auth" />
	<input type="hidden" name="mm" value="$M" />
	<input type="password" name="pass" style="width:184px;" />
	<div style="text-align:right;width:184px;margin-top:8px;">
		<input type="submit" value="OK" style="width:70px;" />
	</div>
	</form>
</div>

<script type="text/javascript">
document.forms[0].pass.focus();
</script>
EOD;
	return uipc_html_layer1($html);
}

function uipc_home($params)
{
	extract($GLOBALS);
	extract($params);
	$t24h = uipc_part_graph_t24h($hours, 28, $t24hAve, $t24hMax, 100);
	$table = '<table cellspacing="0">' . "\n";
	for ($i=0; $i<31; $i++)
	{
		list($k, $v) = each($day);
		if ($i < 28)
		{
			$url = "$BASE?u=$U&m=t24h&o=$i";
			$k = date('m. d <\c\o\d\e>D</\c\o\d\e>.', $k);
			$k = "<a href=\"$url\"><span class=\"sta\">$k</span></a>";
		}
		$up = $dn = null;
		if ($v > 0)
		{
			if ($dayMax == $dayMin)
			{
				$up = '<div class="bar" style="width:100px;"><!----></div>';
			}
			else if ($v < $dayAve)
			{
				$dn = round($v / $dayMax * 99 + 1);
				$dn = '<div class="bar" style="width:' . $dn . 'px;"><!----></div>';
			}
			else
			{
				$dn = round($dayAve / $dayMax * 99 + 1);
				$dn = '<div class="bar" style="width:' . $dn . 'px;"><!----></div>';
				$dn = str_replace('px;', 'px;border-right:0px', $dn);
				$up = round(($v-$dayAve) / $dayMax * 99 + 1);
				$up = '<div class="bar" style="width:' . $up . 'px;"><!----></div>';
			}
		}
		$v = ($i==29 && $v<10) ? sprintf('%0.2f', $v) : number_format($v);
		if ($i > 27)
		{
			$v = '<span class="cur">' . $v . '</span>';
			$up = str_replace('bar', 'barCur', $up);
			$dn = str_replace('bar', 'barCur', $dn);
		}
		$css = ($i > 27) ? ' class="right"' : null;
		$table .=<<< EOD
<tr>
	<td$css>$k</td>
	<td class="count">$v</td>
	<td class="barBgAve">$dn</td>
	<td class="barBg">$up</td>
</tr>\n
EOD;
		if ($i==27 || ereg('Sat', $k))
		{
			$table .=<<< EOD
<tr style="height:8px;">
	<td colspan="2"></td>
	<td class="barBgAve"></td><td class="barBg"></td>
</tr>\n
EOD;
		}
	}
	$t28d = $table . '</table>';
	$table = '<table cellspacing="0">' . "\n";
	for ($i=0; $i<13; $i++)
	{
		list($k, $v) = each($month);
		if ($i < 12)
		{
			$k = "<code>$k</code>.";
		}
		$up = $dn = null;
		if ($v > 0)
		{
			if ($monthMax == $monthMin)
			{
				$up = '<div class="bar" style="width:100px;"><!----></div>';
			}
			else if ($v < $monthAve)
			{
				$dn = round($v / $monthMax * 99 + 1);
				$dn = '<div class="bar" style="width:' . $dn . 'px;"><!----></div>';
			}
			else
			{
				$dn = round($monthAve / $monthMax * 99 + 1);
				$dn = '<div class="bar" style="width:' . $dn . 'px;"><!----></div>';
				$dn = str_replace('px;', 'px;border-right:0px', $dn);
				$up = round(($v-$monthAve) / $monthMax * 99 + 1);
				$up = '<div class="bar" style="width:' . $up . 'px;"><!----></div>';
			}
		}
		$v = number_format($v);
		if ($i == 12)
		{
			$v = '<span class="cur">' . $v . '</span>';
			$up = str_replace('bar', 'barCur', $up);
			$dn = str_replace('bar', 'barCur', $dn);
		}
		$table .=<<< EOD
<tr>
	<td class="right">$k</td>
	<td class="count">$v</td>
	<td class="barBgAve">$dn</td>
	<td class="barBg">$up</td>
</tr>\n
EOD;
		if ($i == 11)
		{
			$table .=<<< EOD
<tr style="height:8px;">
	<td colspan="2"></td>
	<td class="barBgAve"></td><td class="barBg"></td>
</tr>\n
EOD;
		}
	}
	$t12m = $table . '</table>';
	$table = '<table cellspacing="0">' . "\n";
	foreach ($week as $k => $v)
	{
		$up = $dn = null;
		if ($v > 0)
		{
			if ($weekMax == $weekMin)
			{
				$up = '<div class="bar" style="width:100px;"><!----></div>';
			}
			else if ($v < $dayAve)
			{
				$dn = round($v / $weekMax * 99 + 1);
				$dn = '<div class="bar" style="width:' . $dn . 'px;"><!----></div>';
			}
			else
			{
				$dn = round($dayAve / $weekMax * 99 + 1);
				$dn = '<div class="bar" style="width:' . $dn . 'px;"><!----></div>';
				$dn = str_replace('px;', 'px;border-right:0px', $dn);
				$up = round(($v-$dayAve) / $weekMax * 99 + 1);
				$up = '<div class="bar" style="width:' . $up . 'px;"><!----></div>';
			}
		}
		$v = number_format($v);
		$table .=<<< EOD
<tr>
	<td class="right"><code>$k</code>.</td>
	<td class="count">$v</td>
	<td class="barBgAve">$dn</td>
	<td class="barBg">$up</td>
</tr>\n
EOD;
	}
	$week = $table . '</table>';
	$table = '<table cellspacing="0">' . "\n";
	foreach ($vcnt as $k => $v)
	{
		$bar = round($v / $vcntMax * 99 + 1);
		$bar = '<div class="bar" style="width:' . $bar . 'px;"><!----></div>';
		$val = number_format($v);
		$table .=<<< EOD
<tr>
	<td class="right">$k</td>
	<td class="count">$val</td>
	<td class="barBg">$bar</td>
</tr>\n
EOD;
	}
	$vcnt = $table . '</table>';
	$table = '<table cellspacing="0">' . "\n";
	foreach ($vspn as $k => $v)
	{
		$bar = round($v / $vspnMax * 99 + 1);
		$bar = '<div class="bar" style="width:' . $bar . 'px;"><!----></div>';
		$val = number_format($val);
		$table .=<<< EOD
<tr>
	<td class="right">$k</td>
	<td class="count">$v</td>
	<td class="barBg">$bar</td>
</tr>\n
EOD;
	}
	$vspn = $table . '</table>';
	$val = sprintf("%01.1f", 100 * $java);
	$bar = round(100 * $java);
	$bar = '<div class="bar" style="width:' . $bar . 'px;"><!----></div>';
	$table = '<table cellspacing="0">' . "\n";
		$table .=<<< EOD
<tr>
	<td class="right">有効率</td>
	<td class="count" style="padding-right:4px;">$val</td>
	<td class="barBg" style="width:80px;">$bar</td>
</tr>\n
EOD;
	$java = $table . '</table>';
	$engn = gsort($engn);
	$engn = uipc_part_graph($engn, 100, 12, 36, 'left', 'engn');
	arsort($page);
	$page = uipc_part_graph($page, 100, 8, 40, 'left', 'page');
	arsort($refr);
	$refr = uipc_part_graph($refr, 100, 8, 40, 'left', 'refr');
	arsort($host);
	$host = uipc_part_graph($host, 100, 8, 20, 'left', 'host');
	arsort($phrs);
	$phrs = uipc_part_graph($phrs, 100, 8, 28, 'left', 'phrs');
	arsort($word);
	$word = uipc_part_graph($word, 100, 8, 28, 'left', 'word');
	arsort($agnt);
	$agnt = uipc_part_graph($agnt, 100, 8, 28, 'left', 'agnt');
	arsort($osys);
	$osys = uipc_part_graph($osys, 100, 8, 28, 'left', 'osys');
	$lang = order_lang($lang);
	$lang = uipc_part_graph($lang, 100, 4, 36, 'left', 'lang');
	arsort($area);
	$area = uipc_part_graph($area, 100, 8, 20, 'right', 'area');
	arsort($scrs);
	$scrs = uipc_part_graph($scrs, 100, 8, 10, 'right', 'scrs');
	arsort($scrd);
	$scrd = uipc_part_graph($scrd, 100, 8, 10, 'right', 'scrd');
	$today = number_format($day['今日']);
	$tag = $params['tag'];
	$html =<<< EOD
<div class="unitHead" style="letter-spacing:0pt;margin:0px 16px 16px 16px;">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />今日
	<span class="cur">$today</span>
	<span style="margin-left:16px;"><!----></span>
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_TOTL
	<span class="cur">$str_total</span>
	<span style="margin-left:16px;"><!----></span>
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_SPAN
	<span class="cur">$str_span</span> 日 since $str_start
</div>

<div class="unitHead" style="letter-spacing:0pt;margin:0px 16px 16px 16px;">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />解析対象 URL
	<span style="margin-left:12px;"><a href="$target_url"
		target="_blank"><code class="cur">$target_url</code></a></span>
</div>

<table cellspacing="0" class="outer">
<tr>
	<td class="outerCell">
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_T24H</div>
<div class="unitBody">
$t24h
</div>
<!-- ******** -->
	</td>
	<td class="outerCell">
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_T28D</div>
<div class="unitBody">
$t28d
</div>
<!-- ******** -->
	</td>
	<td class="outerCell">
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_T12M</div>
<div class="unitBody">
$t12m
</div>
<!-- ******** -->
	<div style="width:0px;height:8px;"><!----></div>
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_WEEK</div>
<div class="unitBody">
$week
</div>
<!-- ******** -->
	<div style="width:0px;height:8px;"><!----></div>
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_VCNT</div>
<div class="unitBody">
$vcnt
<div style="margin-top:4px;">since $vcntRev</div>
</div>
<!-- ******** -->
	</td>
</tr>
</table>

<table cellspacing="0" class="outer">
<tr>
	<td class="outerCell" colspan="2">
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_PAGE</div>
<div class="unitBody">
$page
</div>
<!-- ******** -->
	</td>
	<td class="outerCell">
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_VSPN</div>
<div class="unitBody">
$vspn
</div>
<!-- ******** -->
	</td>
</tr>
<tr>
	<td class="outerCell" colspan="2">
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_REFR</div>
<div class="unitBody">
$refr
</div>
<!-- ******** -->
	</td>
	<td class="outerCell">
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_HOST</div>
<div class="unitBody">
$host
</div>
<!-- ******** -->
	</td>
</tr>
</table>

<table cellspacing="0" class="outer">
<tr>
	<td class="outerCell">
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_SWD1</div>
<div class="unitBody">
$phrs
</div>
<!-- ******** -->
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_SWD2</div>
<div class="unitBody">
$word
</div>
<!-- ******** -->
	</td>
	<td class="outerCell">
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_ENGN</div>
<div class="unitBody">
$engn
</div>
<!-- ******** -->
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_LANG</div>
<div class="unitBody">
$lang
</div>
<!-- ******** -->
	</td>
</tr>
</tr>
</table>

<table cellspacing="0" class="outer">
<tr>
	<td class="outerCell">
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_AGNT</div>
<div class="unitBody">
$agnt
</div>
<!-- ******** -->
	</td>
	<td class="outerCell">
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_OSYS</div>
<div class="unitBody">
$osys
</div>
<!-- ******** -->
	</td>
</tr>
</table>

<table cellspacing="0" class="outer">
<tr>
	<td class="outerCell">
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_AREA</div>
<div class="unitBody">
$area
</div>
<!-- ******** -->
	</td>
	<td class="outerCell">
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_SCRS</div>
<div class="unitBody">
$scrs
</div>
<!-- ******** -->
	</td>
	<td class="outerCell">
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />$TITLE_SCRD</div>
<div class="unitBody">
$scrd
</div>
<!-- ******** -->
<div style="width:0px;height:8px;"><!----></div>
<!-- ******** -->
<div class="unitHead">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />JavaScript</div>
<div class="unitBody">
$java
</div>
<!-- ******** -->
	</td>
</tr>
</table>

<div style="text-align:center;margin-bottom:8px;">
	<a href="http://$WEBSITE" target="_blank"
		style="color:#333333;"><code>apricot.php</code> ver. $VERSION</a>
</div>

<div style="display:none;">
$tag
</div>
EOD;
	$jump =<<< EOD
<a href="$BASE?u=$U&m=help"><span class="cur">ヘルプ</span></a>
<span style="margin-left:8px;"><!----></span>
<a href="$BASE?u=$U&m=hist"><span class="cur">$TITLE_HIST</span></a>
<span style="margin-left:8px;"><!----></span>
<a href="$BASE?u=$U&m=cnfg"><span class="cur">オプション</span></a>
EOD;
	return uipc_html_layer2($html, $jump);
}

function uipc_t24h($hours, $offset, $t24hAve, $t24hMax)
{
	extract($GLOBALS);
	$table = uipc_part_graph_t24h($hours, $offset, $t24hAve, $t24hMax, 200);
	return uipc_html_layer3($TITLE_T24H, $table);
}

function uipc_graph($title, $hash, $align='left')
{
	extract($GLOBALS);
	$table = uipc_part_graph($hash, 200, 66563, 60, $align, $M);
	return uipc_html_layer3($title, $table);
}

function uipc_hist($lines, $offset)
{
	extract($GLOBALS);
	$unit = 50;
	$from = max(0, $offset * $unit);
	$till = min(count($lines), ($offset<0) ? $LOGMAX : $from+$unit);
	$style = 'style="width:56px;"';
	$html = null;
	for ($i=$from; $i<$till; $i++)
	{
		$hists = explode('<>', $lines[$i]);
		$epoc = isset($hists[0]) ? $hists[0] : null;
		$addr = isset($hists[1]) ? $hists[1] : null;
		$x_addr = isset($hists[2]) ? $hists[2] : null;
		$host = isset($hists[3]) ? $hists[3] : null;
		$x_host = isset($hists[4]) ? $hists[4] : null;
		$refr = isset($hists[5]) ? $hists[5] : null;
		$agnt = isset($hists[6]) ? $hists[6] : null;
		$scrn = isset($hists[7]) ? $hists[7] : null;
		$vcnt = isset($hists[8]) ? $hists[8] : null;
		$vspn = isset($hists[9]) ? $hists[9] : null;
		$lang = isset($hists[10]) ? $hists[10] : null;
		$c_id = isset($hists[11]) ? $hists[11] : null;
		$date = date('H:i - D. m/d', $epoc);
		$html .= "<code class=\"str\">$date</code>\n";
		$html .= "<div class=\"block\" style=\"white-space:nowrap;\">\n";
		if ($c_id)
		{
			$html .= "\t<span $style>clientID : </span>";
			$code = md5($c_id);
			for ($j=0; $j<5; $j++)
			{
				$col = substr($code, $j*6, 6);
				$html .= "<span style=\"color:#$col;\">&#9619;</span>";
			}
			$html .= " <code>$c_id</code><br />\n";
		}
		$html .= "\t<span $style>client : </span>";
		$html .= "<code>$addr</code>";
		$html .= ($host ? " : $host" : null) . "<br />\n";
		if ($x_addr)
		{
			$html .= "\t<span class=\"red\" $style>proxy : </span>";
			$html .= "<code>$x_addr</code>";
			$html .= ($x_host ? " : $x_host" : null) . "<br />\n";
		}
		$html .= "\t<span $style>agent : </span>$agnt<br />\n";
		if ($scrn)
		{
			$html .= "\t<span $style>screen : </span>$scrn<br />\n";
		}
		if ($lang)
		{
			if ($lang != 'ja')
			{
				$lang = "<span class=\"red\">$lang</span>";
			}
			$html .= "\t<span $style>language : </span>$lang<br />\n";
		}
		if ($vspn)
		{
			$html .= "\t<span $style>lasttime : </span>";
			$html .= date('m/d H:i', $epoc-$vspn);
			$html .= ' (' . number_format($vspn) . ' sec ago)';
			if ($vcnt > 1)
			{
				$html .= " <span class=\"red\">$vcnt</span> times";
			}
			$html .= "<br />\n";
		}
		if ($refr)
		{
			$html .= "\t<span $style>referer : </span>$refr<br />\n";
		}
		$html .= "</div>\n\n";
	}
	if (count($lines) > $unit)
	{
		$prefix = "$BASE?u=$U&m=$M";
		$till = ceil(count($lines) / $unit);
		$links = null;
		for ($i=0; $i<$till; $i++)
		{
			$num = sprintf('%02d', $i);
			if ($i == $offset)
			{
				$style = '"text-decoration:underline;"';
				$links .= "<span style=$style>$num</span>";
			}
			else
			{
				$url = "$prefix&o=$i";
				$links .= "<a href=\"$url\"><span class=\"cur\">$num</span></a>";
			}
			$links .= "\n&nbsp;";
		}
		if ($offset < 0)
		{
			$links .= "ALL\n";
		}
		else
		{
			$links .= "<a href=\"$prefix&o=-1\"><span class=\"cur\">ALL</span></a>\n";
		}
		$links .= "<br />\n";
		$html = "$links<br />\n$html$links";
	}
	return uipc_html_layer3('アクセスログ', $html);
}

function uipc_help()
{
	extract($GLOBALS);
	$html =<<< EOD
<div class="subtitle">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />TIPS</div>
<div style="margin:8px 0px 32px 32px;line-height:11pt;">

<span class="str">検索ロボットに巡回されないようにする</span>
<div class="block">
	&quot;オプション&quot; で &quot;パスワード&quot; を設定し、
	&quot;統計ページをパスワードで保護する&quot; を有効します。<br />
	検索ロボットはもちろん、他人から見られることもなくなります。<br />
</div>

<span class="str">パスワード</span>
<div class="block">
	各ユーザのパスワードは、暗号化された状態で保存されます。<br />
	したがって管理者といえども、ユーザのパスワードを知ることはできません。<br />
	ただし管理者はユーザのパスワードの代わりとして、<br />
	管理者パスワードを利用できるため、
	各ユーザの設定を閲覧・変更することができます。<br />
</div>

<span class="str">携帯電話からのアクセスがカウントされない？</span>
<div class="block">
	"解析タグ" を非表示タグで囲んで不可視にしている場合は、
	一部のアクセスがカウントされないことがあります。<br />
	また携帯電話の多くはプロキシのキャッシュを再利用するため、<br />
	その際のアクセスはカウントされないかもしれません。
</div>

</div>

<div class="subtitle">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />項目解説</div>
<div style="margin:8px 0px 32px 32px;line-height:11pt;">

<span class="str">$TITLE_TOTL</span>
<div class="block">
	解析日からのアクセス数の累計です。<br />
	同一閲覧者の <span class="str">$UNIQ</span>
	時間以内の再来訪はカウントしません。<br />
	閲覧者の識別には、IP アドレス・ブラウザ名を使用します。<br />
	<div style="margin:4px 0px 0px 16px;">
		※ <span class="str">$UNIQ</span> はオプションで変更できます<br />
	</div>
</div>
<span class="str">$TITLE_T24H</span>
<div class="block">
	今日の時間ごとのアクセス数の推移です。<br />
	同時に過去28日間の時間ごとのアクセス数の平均と比較します。<br />
</div>
<span class="str">$TITLE_T28D</span>
<div class="block">
	過去28日間の日ごとのアクセス数の推移です。<br />
	濃い背景色は過去28日間の平均値です。<br />
	さらに日付をクリックすると、時間ごとの推移を表示できます。<br />
</div>
<span class="str">$TITLE_T12M</span>
<div class="block">
	過去12ヶ月のアクセス数の推移です。<br />
	濃い背景色は過去12ヶ月間の平均値です。<br />
</div>
<span class="str">$TITLE_WEEK</span>
<div class="block">
	過去28日間の曜日ごとのアクセス数の平均です。<br />
	濃い背景色は過去28日間の平均値です。<br />
</div>
<span class="str">$TITLE_PAGE</span>
<div class="block">
	閲覧されたページの統計です。<br />
	複数のページに解析タグを設定している場合に閲覧量を比較できます。<br />
	例外的に同一閲覧者の <span class="str">$UNIQ</span>
	時間以内の再来訪もカウントします。<br />
	&quot;オプション&quot; でサーバ名を指定している場合は、
	サーバ名が省略されます。<br />
	（最大 $LOGMAX 件）<br />
</div>
<span class="str">$TITLE_REFR</span>
<div class="block">
	解析対象のページを閲覧する直前のページの統計です。<br />
	通常、リファラには解析対象ページへのリンクが存在します。<br />
	&quot;オプション&quot; でサーバ名を指定している場合は、
	サーバ名が省略されます。<br />
	（最大 $LOGMAX 件）<br />
</div>
<span class="str">$TITLE_VCNT</span>
<div class="block">
	閲覧者の来訪回数の統計です。<br />
	閲覧者の来訪回数は個々の Cookie に保存されています。<br />
	Cookie が利用できない閲覧者を考慮して１回目の来訪は統計をとりません。<br />
	最大表\示件数に上限はありません。<br />
</div>
<span class="str">$TITLE_VSPN</span>
<div class="block">
	閲覧者の来訪間隔の統計です。<br />
	閲覧者の最終来訪日時は個々の Cookie に保存されています。<br />
	（最大 $LOGMAX 件）<br />
</div>
<span class="str">$TITLE_HOST</span>
<div class="block">
	閲覧者のドメインの統計です。<br />
	ドメインから閲覧者のプロバイダ (ISP) を推測することができます。<br />
	（最大 $LOGMAX 件）<br />
</div>
<span class="str">$TITLE_SWD1</span>
<div class="block">
	閲覧者が検索サイトを利用した際の検索キーワードの組合せの統計です。<br />
	文字コード、全／半角文字、大／小文字を統一し、単語をソートしています。<br />
	キャッシュ検索の場合は、キャッシュの URL をリファラとして記録し、<br />
	検索キーワードは記録しません。<br />
	（最大 $LOGMAX 件）<br />
</div>
<span class="str">$TITLE_SWD2</span>
<div class="block">
	$TITLE_SWD1 に含まれる単語の統計です。<br />
	表示件数は $TITLE_SWD1 のデータ内容に依存します。<br />
</div>
<span class="str">$TITLE_ENGN</span>
<div class="block">
	閲覧者が利用した検索エンジンの統計です。<br />
	（最大 $LOGMAX 件）<br />
</div>
<span class="str">$TITLE_AGNT</span>
<div class="block">
	ブラウザからサーバへ送信されるエージェント名に含まれる
	ブラウザ種別の統計です。<br />
	JavaScript により取得できた場合はこれを優先します。<br />
	（最大 $LOGMAX 件）<br />
</div>
<span class="str">$TITLE_OSYS</span>
<div class="block">
	ブラウザからサーバへ送信されるエージェント名に含まれる
	OS種別の統計です。<br />
	JavaScript により取得できた場合はこれを優先します。<br />
	（最大 $LOGMAX 件）<br />
</div>
<span class="str">$TITLE_AREA</span>
<div class="block">
	閲覧者の都道府県の統計です。<br />
	ドメインに都道府県名を含む一部のプロバイダ
	(ISP) だけを対象としています。<br />
	したがって統計として正確とは言えませんが、おおよその参考になります。<br />
	（最大 $LOGMAX 件）<br />
</div>
<span class="str">$TITLE_SCRS</span>
<div class="block">
	閲覧者のスクリーンサイズの統計です。<br />
	データの取得には JavaScript を使用しています。<br />
	（最大 $LOGMAX 件）<br />
</div>
<span class="str">$TITLE_SCRD</span>
<div class="block">
	閲覧者のスクリーンの色深度の統計です。<br />
	データの取得には JavaScript を使用しています。<br />
	（最大 $LOGMAX 件）<br />
</div>

</div>

<div class="subtitle">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />技術情報</div>
<div style="margin:8px 0px 32px 32px;line-height:11pt;">

<span class="str">サーバにかかる負荷</span>
<div class="block">
	ページを表示するまでの処理時間が、
	出力される HTML の 2 行目にコメントとして記述されます。<br />
	サーバへの負荷が大きいと感じる場合はソースコード内の &quot;ログの長さ&quot;
	を短めに設定しなおしてください。<br />
	稼働中であっても変更可能です。
</div>

<span class="str">検索キーワードの文字化けについて</span>
<div class="block">
	検索キーワードはそれが日本語である限り、
	文字化けしないように文字コードが変換されます。<br />
	文字化けを起こす場合は <code>phpinfo()</code> で
	サーバの PHP の <code>mbstring</code> 設定<br />
	<code>'--enable-mbstring'</code> を確認してみてください。
</div>

<span class="str">プロキシ</span>
<div class="block">
	プロキシ検出には以下の環境変数を利用します。<br />
	また可能であれば、閲覧者のIPアドレスを特定します。<br />
	<br />
	プロキシ検出に利用される環境変数<br />
	<div style="margin:2px 0px 0px 32px;">
		<code>
		HTTP_CACHE_INFO, HTTP_FROM,
		HTTP_IF_MODIFIED_SINCE,
		HTTP_MAX_FORWARDS,<br />
		HTTP_PROXY_AUTHORIZATION,
		HTTP_PROXY_CONNECTION,
		HTTP_TE,
		HTTP_X_HTX_AGENT,<br />
		HTTP_X_LOCKING,
		HTTP_XONNECTION,
		HTTP_XROXY_CONNECTION<br />
		</code>
	</div>
	<br />
	プロキシ検出に利用され、さらに
	閲覧者の情報を含む可能性のある環境変数<br />
	<div style="margin:2px 0px 0px 32px;">
		<code>
		HTTP_CACHE_CONTROL,
		HTTP_CLIENT_IP,
		HTTP_FORWARDED,
		HTTP_REMOTE_HOST_WP,<br />
		HTTP_SP_HOST,
		HTTP_VIA,
		HTTP_X_CISCO_BBSM_CLIENTIP,
		HTTP_X_FORWARDED_FOR<br />
		</code>
	</div>
	<br />
	プロキシ検出に利用されない環境変数 (パーソナルプロキシなど)<br />
	<div style="margin:2px 0px 0px 32px;">
		<code>
		HTTP_EXTENSION,
		HTTP_WEFERER,
		HTTP_WSER_AGENT,
		HTTP_XXXXXXXXXXXXXXX<br />
		</code>
	</div>
</div>

<span class="str">各ユーザのアクセス回数</span>
<div class="block">
	各ユーザの統計ページが閲覧された回数は
	<a href="$BASE"><span class="cur">トップページ</span></a>
	の &quot;アクセス／週&quot; に表示されます。<br />
	有効利用されていないユーザアカウントを知る上で参考になります。
</div>

<span class="str">ユーザとデータディレクトリ</span>
<div class="block">
	各ユーザのデータは完全に独立し、他との依存関係は全くありません。<br />
	データディレクトリ内のユーザディレクトリを削除するとユーザが削除され、<br />
	ユーザ名のディレクトリを作成するとユーザが追加されます。<br />
	<div class="block">
		<code style="width:140px;">$BASE</code>プログラム本体<br />
		<code style="width:140px;">$BASE.data/</code>データディレクトリ<br />
		<div style="margin-left:32px;">
			├─ <code style="width:100px;">$ADMIN/</code>
				ユーザディレクトリ （管理者）<br />
			├─ <code style="width:100px;">user01/</code>
				ユーザディレクトリ<br />
			├─ <code style="width:100px;">user02/</code>
				ユーザディレクトリ<br />
			├─ <code style="width:100px;">user03/</code>
				ユーザディレクトリ<br />
			├─ <code style="width:100px;">user04/</code>
				ユーザディレクトリ<br />
		</div>
	</div>
</div>

<span class="str">データのバックアップ / サーバの移転</span>
<div class="block">
	すべての統計情報・設定情報はディレクトリ <code>$BASE.data</code>
	以下に保存されます。<br />
	このディレクトリ以下をバックアップしておけば、
	いつでもその時の状態に復元できます。
</div>

<span class="str">アンインストールする</span>
<div class="block">
	アンインストールは <a href="$BASE"><span class="cur">トップページ</span></a>
	から行うことができます。<br />
	アンインストールを実行するためには管理者パスワードが必要です。
</div>

</div>
EOD;
	return uipc_html_layer2($html);
}

function uipc_cnfg($tag_pc, $tag_cp, $ignr)
{
	extract($GLOBALS);
	$tag_pc = nl2br(htmlentities($tag_pc));
	$tag_cp = nl2br(htmlentities($tag_cp));
	$check_rfpf = $RFPF ? ' checked="checked"' : null;
	$check_rfeg = $RFEG ? ' checked="checked"' : null;
	$check_auth = $AUTH ? ' checked="checked"' : null;
	$check_redt = $REDT ? ' checked="checked"' : null;
	$check_ignr = $ignr ? ' checked="checked"' : null;
	$html =<<< EOD
<div class="subtitle">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />オプション</div>
<div style="margin:8px 0px 32px 32px;">
	<form action="$BASE" method="post" style="margin:0px;">
	<input type="hidden" name="u" value="$U" />
	<input type="hidden" name="m" value="cnfg_set" />
	
	<div class="str">解析対象ページのサーバ名</div>
	<div class="block">
		<input type="text" name="pfx" value="$PFX" style="width:480px;" />
		<div style="margin:4px;">
			サーバ名を指定しない場合は、無条件にすべての URL をカウントします。<br />
			サーバ名を指定した場合は、
			サーバ名を URL の先頭に含むページだけをカウントします。<br />
			半角空白を区切りとして複数のサーバ名を指定できます。<br />
			ワイルドカード * を利用することもできます。<br />
		</div>
		<div class="block">
			<span  style="margin-right:8px;">指定例：</span>
			<code>
				lovpop.net
				www.lovpop.net
				<span class="str">*.</span>lovpop.net<span class="str">/cgi-bin</span>
			</code>
		</div>
	</div>
	
	<input type="checkbox" name="rfpf"$check_rfpf />
	$TITLE_REFR に &quot;解析対象ページのサーバ名&quot;
	から始まる URL を記録する
	<div class="block"></div>
	
	<input type="checkbox" name="rfeg"$check_rfeg />
	$TITLE_REFR に &quot;検索エンジン&quot; を記録する
	<div class="block"></div>
	
	<input type="checkbox" name="auth"$check_auth />
	統計ページをパスワードで保護する
	<div class="block"></div>
	
	<input type="checkbox" name="ignr"$check_ignr />
	このブラウザからのアクセスはカウントしない
	<div class="block"></div>
	
	<input type="checkbox" name="redt"$check_redt />
	別のサイトへ移動する際に足跡 （リファラ） を残さない
	<div class="block"></div>
	
	<div style="padding:8px 0px 0px 0px;"></div>
	
	<div class="str">同じ閲覧者を再びカウントするまでの時間</div>
	<div class="block">
		<input type="text" name="uniq" value="$UNIQ"
			style="width:70px;margin-right:8px;" />
		時間 (1～24)
	</div>
	
	<div class="str">ページカラー 濃 ／ 薄</div>
	<div class="block">
		<input type="text" name="col1" value="#$COL1"
			style="width:70px;margin-right:8px;" />
		<input type="text" name="col2" value="#$COL2"
			style="width:70px;margin-right:8px;" />
		基本色： #$COL1x ／ #$COL2x
	</div>
	
	<div class="str">連絡先メールアドレス</div>
	<div class="block">
		<input type="text" name="mail" value="$MAILADDR" style="width:160px;" />
	</div>
	
	<div class="str">パスワードの変更</div>
	<div class="block">
		<input type="password" name="pass1" style="width:160px;margin-right:8px;" />
		<input type="password" name="pass2" style="width:160px;margin-right:8px;" />
		※ 確認のため２回入力
	</div>
	
	<div class="str">パスワード</div>
	<div class="block">
		<input type="password" name="pass" style="width:160px;margin-right:8px;" />
		<input type="submit" value="適用" style="width:100px;margin-right:8px;" />
		※ 管理者パスワードも有効です
	</div>
	</form>
</div>

<div class="subtitle">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />リセット</div>
<div style="margin:8px 0px 32px 32px;">
	<form action="$BASE" method="post" style="margin:0px;">
	<input type="hidden" name="u" value="$U" />
	<input type="hidden" name="m" value="cnfg_reset" />
	<input type="checkbox" name="del_confirm"
		onClick="check();" onkeypress="check();" />
	次のデータをリセットする
	<div class="block">
		<table cellspacing="0">
		<tr>
			<td style="padding-left:16px;">
				<input type="checkbox" name="del_data" disabled="disabled" />
				アクセス数・推移データ
			</td>
			<td style="padding-left:16px;">
				<input type="checkbox" name="del_page" disabled="disabled" />
				閲覧ページ
			</td>
			<td style="padding-left:16px;">
				<input type="checkbox" name="del_vcnt" disabled="disabled" />
				来訪回数
			</td>
			<td style="padding-left:16px;">
				<input type="checkbox" name="del_area" disabled="disabled" />
				都道府県
			</td>
		</tr>
		<tr>
			<td style="padding-left:16px;">
				<input type="checkbox" name="del_srch" disabled="disabled" />
				検索エンジン・検索キーワード
			</td>
			<td style="padding-left:16px;">
				<input type="checkbox" name="del_refr" disabled="disabled" />
				リファラ
			</td>
			<td style="padding-left:16px;" colspan="2">
				<input type="checkbox" name="del_hist" disabled="disabled" />
				来訪間隔・ドメイン・ブラウザ・OS・画面
			</td>
		</tr>
		</table>
	</div>
	<div class="str">パスワード</div>
	<div class="block">
		<input type="password" name="pass"
			style="width:160px;margin-right:8px;" disabled="disabled" />
		<input type="submit" name="submit" value="リセット"
			style="width:100px;margin-right:8px;" disabled="disabled" />
		※ 管理者パスワードも有効です
	</div>
	</form>
</div>

<script type="text/javascript">
function check()
{
	var status = ! document.forms[1].del_confirm.checked;
	document.forms[1].del_data.disabled = status;
	document.forms[1].del_page.disabled = status;
	document.forms[1].del_vcnt.disabled = status;
	document.forms[1].del_area.disabled = status;
	document.forms[1].del_srch.disabled = status;
	document.forms[1].del_refr.disabled = status;
	document.forms[1].del_hist.disabled = status;
	document.forms[1].pass.disabled = status;
	document.forms[1].submit.disabled = status;
}
</script>

<div class="subtitle">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />解析タグ</div>
<div style="margin:8px 0px 32px 32px;">
	解析対象のページの HTML の適当な箇所に次のタグを追加します。<br />
	追加するべき場所がわからない場合は
	<code>&lt;body&gt;</code> の直後に追加します。<br />
	<div style="margin:16px 0px 16px 32px;">
		<code class="str">
$tag_pc
		</code>
	</div>
	携帯電話専用サイトには上記タグの代わりとして以下のタグを追加します。
	<div style="margin:16px 0px 16px 32px;">
		<code class="str">
$tag_cp
		</code>
	</div>
	さらに <code>&lt;div style="display:none;"&gt; ～ &lt;/div&gt;</code>
	タグで囲むと非表示にできますが、<br />
	一部の携帯電話からのアクセスが解析できなくなります。<br />
</div>\n
EOD;
	return uipc_html_layer2($html);
}

function uipc_initialize()
{
	extract($GLOBALS);
	$user_name = ($U == 'admin') ? '管理者' : "ユーザ <code>'$U'</code> の";
	$html =<<< EOD
	<form action="$BASE" method="post" style="margin:0px;">
	$user_name パスワードを作成します。確認のため２回入力します。<br />
	<div style="margin:8px 0px 32px 32px;">
		<input type="hidden" name="u" value="$U" />
		<input type="hidden" name="m" value="cnfg_set" />
		<input type="hidden" name="init" value="on" />
		<input type="hidden" name="pass" value="" />
		<input type="password" name="pass1" value=""
			style="width:160px;margin-right:8px;" />
		<input type="password" name="pass2" value=""
			style="width:160px;margin-right:8px;" />
	</div>
	メールアドレス<br />
	<div style="margin:8px 0px 32px 32px;">
		<input type="text" name="mail" value=""
			style="width:160px;margin-right:8px;" />
		<input type="submit" value="OK" style="width:100px;" />
		<div style="width:0px;height:8px;"><!----></div>
		※ パスワード・メールアドレスはいつでも変更できます
	</div>
	</form>
EOD;
	return uipc_html_layer3('設定の初期化', $html, '<!---->');
}

function uipc_index($params, $ignr)
{
	extract($GLOBALS);
	extract($params);
	$check_auth = $AUTH ? ' checked="checked"' : null;
	$check_ignr = $ignr ? ' checked="checked"' : null;
	$user_total = count($USERS) - 1;
	if ($user_total == 0)
	{
		$table = '※ まずはユーザを追加してください ↓';
	}
	else
	{
		$pad08 = 'style="vertical-align:middle;padding-left:8px;"';
		$pad12 = 'style="vertical-align:middle;padding-left:12px;"';
		sort($USERS);
		$table = null;
		foreach ($USERS as $user)
		{
			if ($user == $ADMIN)
			{
				continue;
			}
			$addr_usr = $addr[$user];
			$span_num = number_format($span[$user]);
			$totl_num = number_format($totl[$user]);
			$av28_num = number_format($av28[$user]);
			$view_num = number_format($view[$user]);
			$av28_bar = round($av28[$user] / $av28_max * 79 + 1) . 'px';
			$view_bar = round($view[$user] / $view_max * 79 + 1) . 'px';
			$table .=<<< EOD
	<tr>
		<td $pad08><a href="$BASE?u=$user"
			target="_blank"><img src="$BASE?u=$user&m=cube" alt="□"
			class="cube" /><span class="cur">$user</span></a></td>
		<td class="count" $pad12>$span_num</td>
		<td class="count" $pad12>$totl_num</td>
		<td class="count" $pad12>$av28_num</td>
		<td class="barBg"><div class="bar"
			style="width:$av28_bar;"><!----></div></td>
		<td class="count" $pad12>$view_num</td>
		<td class="barBg"><div class="bar"
			style="width:$view_bar;"><!----></div></td>
		<td $pad12>$addr_usr</td>
	</tr>
EOD;
			$table .= "\n";
		}
		$table = rtrim($table);
		$table =<<< EOD
		<table cellspacing="0">
		<tr>
			<td>ユーザ名</td>
			<td class="right" $pad12>期間</td>
			<td class="right" $pad12>累計</td>
			<td colspan="2" $pad12>28日平均</td>
			<td colspan="2" $pad12>アクセス／週</td>
			<td $pad08>連絡先</td>
		</tr>
		$table
		</table>
EOD;
	}
	$html =<<< EOD
<div class="subtitle">
	<img src="$BASE?u=$ADMIN&m=cube" alt="□" class="cube" />ユーザ管理</div>
<div style="margin:8px 0px 32px 32px;">
	ユーザ数 <span class="str">$user_total</span>
	<span style="margin-left:24px;"><!----></span>
	合計ファイルサイズ <span class="str">$size_total</span> KB
	<span style="margin-left:24px;"><!----></span>
	合計ユーザアクセス／週 <span class="str">$view_total</span> 回
	<div style="width:0px;height:20px;"><!----></div>
	<div style="width:580px;padding:8px 4px;border:1px solid #BFBFBF;">
		<div style="text-align:center;">
$table
		</div>
	</div>
	<div style="width:0px;height:24px;"><!----></div>
	<!-- form -->
	<form action="$BASE" method="post" style="margin:0px;">
	<input type="hidden" name="u" value="$ADMIN" />
	<input type="hidden" name="m" value="set" />
	<div class="str">ユーザ名</div>
	<div class="block">
		<input type="text" name="user_name" value=""
			style="width:160px;margin-right:8px;">
		<input type="radio" name="add_del" value="add" checked="checked" />
			ユーザの追加
		<input type="radio" name="add_del" value="del" />
			ユーザの削除
	</div>
	<div class="str">管理者パスワード</div>
	<div class="block">
		<input type="password" name="pass" style="width:160px;margin-right:8px;" />
		<input type="submit" value="実行" style="width:100px;" />
	</div>
	</form>
</div>

<div class="subtitle">
	<img src="$BASE?u=$ADMIN&m=cube" alt="□" class="cube" />セキュリティ</div>
<div style="margin:8px 0px 32px 32px;">
	<!-- form -->
	<form action="$BASE" method="post" style="margin:0px;">
	<input type="hidden" name="u" value="$ADMIN" />
	<input type="hidden" name="m" value="cnfg_set" />
	<input type="checkbox" name="auth"$check_auth />
	このページをパスワードで保護する<br />
	<input type="checkbox" name="ignr"$check_ignr />
	このブラウザから各ユーザの統計ページへアクセスしても
	&quot;アクセス／週&quot; にカウントしない
	<input type="hidden" name="uniq" value="12" />
	<input type="hidden" name="col1" value="#$COL1s" />
	<input type="hidden" name="col2" value="#$COL2s" />
	<input type="hidden" name="mail" />
	<div class="block"></div>
	<div class="str">管理者メールアドレス</div>
	<div class="block">
		<input type="text" name="mail" style="width:160px" value="$MAILADDR" />
	</div>
	<div class="str">管理者パスワードの変更</div>
	<div class="block">
		<input type="password" name="pass1" style="width:160px;margin-right:8px;" />
		<input type="password" name="pass2" style="width:160px;margin-right:8px;" />
		※ 確認のため２回入力
	</div>
	<div class="str">管理者パスワード</div>
	<div class="block">
		<input type="password" name="pass" style="width:160px;margin-right:8px;" />
		<input type="submit" value="適用" style="width:100px;" />
	</div>
	</form>
</div>

<div class="subtitle">
	<img src="$BASE?u=$U&m=cube" alt="□" class="cube" />アンインストール</div>
<div style="margin:8px 0px 8px 32px;">
	<!-- form -->
	<form action="$BASE" method="post" style="margin:0px;">
	<input type="hidden" name="u" value="$ADMIN" />
	<input type="hidden" name="m" value="uninstall" />
	このアクセス解析プログラム <code>$BASE</code>
	に関わる全てのデータを削除して白紙の状態に戻します<br />
	実行後は二度と元に戻せません<br />
	<br />
	<input type="checkbox" name="uninstall_confirm"
		onClick="check();" onkeypress="check();" />
	アンインストールを実行する
	<div class="block"></div>
	<div class="str">管理者パスワード</div>
	<div class="block">
		<input type="password" name="pass1"
			style="width:160px;margin-right:8px;" disabled="disabled" />
		<input type="password" name="pass2"
			style="width:160px;margin-right:8px;" disabled="disabled" />
		※ 確認のため２回入力<br />
		<div style="padding:8px 0px 0px 0px;"></div>
		<input type="submit" name="submit" value="アンインストール"
			style="width:160px;margin-right:8px;" disabled="disabled" />
		<br />
		<div style="padding:8px 0px 0px 0px;"></div>
		※ アンインストール実行後は <code>$BASE</code> を
			手動で削除してください<br />
	</div>
	</form>
</div>

<script type="text/javascript">
function check()
{
	var status = ! document.forms[2].uninstall_confirm.checked;
	document.forms[2].pass1.disabled = status;
	document.forms[2].pass2.disabled = status;
	document.forms[2].submit.disabled = status;
}
</script>
EOD;
	$jump = "<a href=\"$BASE?u=$U&m=help\"><span class=\"cur\">ヘルプ</span></a>";
	return uipc_html_layer2($html, $jump);
}

function uipc_part_graph($hash, $h_max, $v_max, $width, $align, $id)
{
	extract($GLOBALS);
	$maxVal = max(count($hash) ? array_values($hash) : array(1));
	$align = ($align == 'right') ? ' style="text-align:right;"' : null;
	$table = '<table cellspacing="0">' . "\n";
	foreach ($hash as $k => $v)
	{
		if ($v_max-- > 0)
		{
			$k = ereg_replace('<!.*>', null, $k);
			$k1 = $k2 = $k;
			if (mb_strwidth($k) > $width)
			{
				$k1 = mb_strimwidth($k, 0, $width - 3) . '...';
			}
			if ($k1{0}=='*' && ereg('^\*\.(.+)\.([a-z]+)$', $k1, $r))
			{
				$sld = $r[1];
				$tld = $r[2];
				if ($tld=='jp')
				{
					$sld = ereg_replace('^(.+)\.(co|ac|ad|go|ed|gr|lg)$',
						 '\1.<span class="red">\2</span>', $sld);
				}
				else if ($tld!='com' && $tld!='net')
				{
					$tld = '<span class="red">' . $tld . '</span>';
				}
				$k1 = "*.$sld.$tld";
				$k2 = substr_replace($k, 'http://www', 0, 1);
			}
			if (ereg('^https?://', $k2))
			{
				if ($REDT)
				{
					$k2 = "$BASE?j=" . rawurlencode(substr($k2, 7));
				}
				$k1 = '<a href="' . $k2 . '" target="_blank">' . $k1. '</a>';
			}
			$bar = round($v / $maxVal * ($h_max - 1) + 1) . 'px;';
			$table .=<<< EOD
<tr>
	<td$align>$k1</td>
	<td class="count">$v</td>
	<td class="barBg"><div class="bar" style="width:$bar"><!----></div></td>
</tr>\n
EOD;
		}
		else
		{
			$table .=<<< EOD
<tr>
	<td colspan="3" class="count" style="line-height:7pt;"><a
		href="$BASE?u=$U&m=$id"><span class="cur">more</span></a></td>
</tr>\n
EOD;
			break;
		}
	}
	return $table . '</table>';
}

function uipc_part_graph_t24h($hours, $offset, $t24hAve, $t24hMax, $maxlength)
{
	$offset *= 24;
	$hour = date('H', time());
	$table = '<table cellspacing="0">' . "\n";
	for ($i=0; $i<24; $i++)
	{
		$val = $hours[$offset + $i];
		$bar_length = round($val / $t24hMax * ($maxlength-1) + 1) . 'px';
		$bar_class = 'bar';
		$val = number_format($val);
		if ($offset==672 && $i==$hour)
		{
			$val = '<span class="cur">' . $val . '</span>';
			$bar_class = 'barCur';
		}
		$barAve = round($t24hAve[$i] / $t24hMax * ($maxlength-1) + 1) . 'px';
		$h = sprintf('%02d', $i) . 'h';
		$table .=<<< EOD
<tr>
	<td class="top">$h</td>
	<td class="count">$val</td>
	<td class="barBg"><div class="$bar_class"
		style="width:$bar_length;"><!----></div><div class="barAve"
		style="width:$barAve;"><!----></div></td>
</tr>\n
EOD;
	}
	return $table . '</table>';
}
?>