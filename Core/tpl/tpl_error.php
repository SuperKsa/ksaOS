<!DOCTYPE html>
<html>
<head>
	<title>{$title}错误</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="ROBOTS" content="NOINDEX,NOFOLLOW,NOARCHIVE" />
	<style>
	body {background-color:white;color:black;font:9pt/11pt verdana,arial,sans-serif;}
	#container {width:1024px;}
	#message {width:1024px;color:black;}
	.red {color:red;}
	a:link {font:9pt/11pt verdana,arial,sans-serif;color:red;}
	a:visited {font:9pt/11pt verdana,arial,sans-serif;color:#4e4e4e;}
	h1 {color:#FF0000;font:18pt "Verdana";margin-bottom:0.5em;}
	.bg1 {background-color:#edfbf5;}
	.bg2 {background-color:#92e8b1;}
	.table {background:#AAAAAA;font:11pt Menlo,Consolas,"Lucida Console";border-collapse:collapse}
	.info {background:none repeat scroll 0 0 #F3F3F3;border:0px solid #aaaaaa;color:#000000;font-size:11pt;line-height:160%;margin-bottom:1em;padding:1em;}
	td {border:#ddd 1px solid;padding:5px}
	.help {background:#F3F3F3;border-radius:10px 10px 10px 10px;font:12px verdana,arial,sans-serif;text-align:center;line-height:160%;padding:1em;}
	.sql {background:#FFFFCC;border:1px solid #aaaaaa;color:#000000;font:arial,sans-serif;font-size:9pt;line-height:160%;margin-top:1em;padding:4px;}
	</style>
</head>
<body>
	<div id="container">
		<h1>{$title}错误</h1>
		<div class="info">{$Msg}</div>
		{if defined('DEBUGS') && !empty($phpmsg)}
			<h1>错误信息</h1>
			<table cellpadding="0" cellspacing="0" width="100%" class="table">
			{if is_array($phpmsg)}
				<tr class="bg2"><td>File</td><td>Line</td><td>Code</td></tr>
				{loop $phpmsg $msg}
					<tr class="bg1">
					<td>{$msg['file']}</td>
					<td>{$msg['line']}</td>
					<td>{$msg['function']}</td>
					</tr>
				{/loop}
			{else}
				<tr><td><ul>'.$phpmsg.'</ul></td></tr>
			{/if}
			</table>
		{/if}
	</div>
</body>
</html>