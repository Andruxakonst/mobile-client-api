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
	<script type="text/javascript">let userToken = '<?=$user_token?>';<?if($sort_by_coords){?>let coordX = <?=$coord_x?>;let coordY = <?=$coord_y?>;<?}?></script>
</head>
<body onload="onLoad()">
<div id="load_layer"></div>
<div id="header">
<div id="tags">
	<?php
	if ($show_tag_all)
		echo '<div class="tag word all active">Все</div>';
	foreach ($tags as $val) {
		echo '<div class="tag word'.(($show_tag_all)?'':' active').'">'.$val['tag'].'</div>';
	}?>
	<div class="tag back">&#10094;</div>
	<div class="tag name"></div>
</div>
</div>
<div id="content">
<div id="travel_guide_box">
	<?php foreach ($items as $item) {
	echo '<div class="item'.(($item['prime'])?' big':'').'" data-id="'.$item['id'].'">
		<div class="img-preview">
			<img src="'.$item['image_preview'].'">
		</div>
		<div class="title">'.$item['title'].'</div>
		<div class="short-text">'.$item['short_text'].'</div>
	</div>';
	}?>
</div>
<div id="content_item"></div>
</div>
<div id="footer">Вся представленная информация, носит информационный характер и ни при каких условиях не является публичной офертой, определяемой положениями Статьи 437(2) Гражданского кодекса.
</div>
</body>
</html>