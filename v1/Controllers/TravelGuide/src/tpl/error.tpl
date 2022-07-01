<!DOCTYPE html>
<html>
<head>
	<title>Travel Guide</title>
	<meta name="viewport" content="viewport-fit=cover, width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, minimal-ui">
	<?=($android)?'<link href="https://fonts.googleapis.com/css?family=Montserrat:400,500,800" rel="stylesheet">':''?>
	<link rel="stylesheet" type="text/css" href="css/main.css<?=(@$_GET['platform'])?'?platform='.$_GET['platform']:''?>">
	<script type="text/javascript" src="js/jquery.js"></script>
	<script type="text/javascript" src="js/jquery-ui.min.js"></script>
	<script type="text/javascript" src="js/jquery-mobile.min.js"></script>
	<script type="text/javascript" src="js/main.js"></script>
</head>
<body>
<div id="header">
ОШИБКА
</div>
<div id="content">
<?=$error?>
<div id="footer">
</div>
</body>
</html>