*
{
	-webkit-user-select: none;
	-webkit-tap-highlight-color: rgba(0,0,0,0);
	margin: 0;
	padding: 0;
	border: 0;
	outline: 0;
}
html, body
{
	overflow-x: hidden;
}
body
{
	min-height: 100vh;
	font-size: 14px;
	font-family: <?=($android)?'\'Montserrat\', sans-serif':'\'Helvetica\''?>;
	display: flex;
	flex-direction: column;
}
input, textarea {
	-webkit-user-select: auto;
}
.load
{
	overflow: hidden;
}
#load_layer
{
	position: fixed;
	top: 0;
	right: 0;
	bottom: 0;
	left: 0;
	<?=(!$android)?'background-color: rgba(255,255,255,0.5);
	backdrop-filter: blur(20px);
	-webkit-backdrop-filter: blur(10px);':'background-color: rgba(255,255,255,0.8);'?>
	z-index: 100;
	display: none;
}
#header
{
	position: fixed;
	top: 0;
	right: 0;
	left: 0;
	height: 75px;
	padding-top: 15px;
	box-sizing: border-box;
	<?=(!$android)?'background-color: rgba(255,255,255,0.8);
	backdrop-filter: blur(20px);
	-webkit-backdrop-filter: blur(10px);':'background-color: #fff;'?>
	z-index: 2;
}
#tags
{
	padding: 0 13px;
	overflow-x: scroll;
	white-space: nowrap;
	font-size: 0;
}
#tags::-webkit-scrollbar {
	display: none;
}
#tags .tag
{
	display: inline-block;
	background-color: #fff;
	padding: 5px 15px;
	margin: 7px;
	border-radius: 12px;
	line-height: 28px;
	font-size: 18px;
	box-shadow: 0px 0px 7px 0px #777;
	transition: background-color 0.3s ease;
}
#tags .tag.active
{
	background-color: #ddd;
}
#tags .tag.back, #tags .tag.name
{
	display: none;
}
#content
{
	position: relative;
	padding-top: 75px;
	flex: 1 0 auto;
}
#content #travel_guide_box
{
	width: 100%;
	position: relative;
	padding: 5px 10px;
	box-sizing: border-box;
	/*overflow: hidden;*/
}
#content #travel_guide_box .item
{
	float: left;
	width: 50%;
	padding: 10px;
	box-sizing: border-box;
	word-break: break-word;
}
#content #travel_guide_box .item.big
{
	width: 100%;
}
#content #travel_guide_box .item .img-preview
{
	border-radius: 12px;
	overflow: hidden;
}
#content #travel_guide_box .item .img-preview img
{
	width: 100%;
	display: block;
}
#content #travel_guide_box .item .title
{
	font-size: 20px;
	font-weight: 500;
}
#footer
{
	flex-shrink: 0;
	padding: 10px;
	color: #999;
	font-size: 13px;
}
#content_item
{
	/*width: 100%;*/
	/*height: 100vh;*/
	/*position: relative;
	top: 0;
	left: 100%;
	padding: 5px 10px;
	overflow-x: hidden;
	box-sizing: border-box;*/
}
#content_item
{
	position: relative;
	width: 100%;
	margin-top: -85px;
	/*height: 100vh;*/
	top: 0;
	left: 100%;
	padding: 5px 10px;
	overflow-x: hidden;
	box-sizing: border-box;
}
#content_item .image_preview
{
	position: relative;
	margin: -5px -10px 0;
}
#content_item .image_preview img
{
	width: 100%;
	display: block;
}
#content_item .image_preview .text-wrapper
{
	position: absolute;
	right: 0;
	bottom: 0;
	left: 0;
	color: #fff;
	background: linear-gradient(0.0turn,rgba(0,0,0,0.8),rgba(0,0,0,0.5),rgba(0,0,0,0.3),rgba(0,0,0,0.2),rgba(0,0,0,0));
}
#content_item .image_preview .text-wrapper .text
{
	font-size: 30px;
	padding: 20px 10px;
}
#content_item .content
{
	padding: 20px 0;
}
#content_item .content ul
{
	padding: 10px 0 10px 20px;
}
/*Loader [START]*/
.loader-rnd {
	position: absolute;
	top: 50%;
	left: 50%;
	width: 70px;
	height: 70px;
	margin-top: -35px;
	margin-left: -35px;
}
.loader-rnd div {
	box-sizing: border-box;
	display: block;
	position: absolute;
	width: 65px;
	height: 65px;
	margin: 6px;
	border: 6px solid #f9a825;
	border-radius: 50%;
	animation: loader-rnd 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
	border-color: #f9a825 transparent transparent transparent;
}
.loader-rnd div:nth-child(1) {
	animation-delay: -0.45s;
}
.loader-rnd div:nth-child(2) {
	animation-delay: -0.3s;
}
.loader-rnd div:nth-child(3) {
	animation-delay: -0.15s;
}
@keyframes loader-rnd {
	0% {
		transform: rotate(0deg);
	}
	100% {
		transform: rotate(360deg);
	}
}
/*Loader [END]*/
@media (prefers-color-scheme: dark) {
body
{
	background-color: #000;
	color: #fff;
}
#header
{
	<?=(!$android)?'background-color: rgba(0,0,0,0.8);
	backdrop-filter: blur(20px);
	-webkit-backdrop-filter: blur(10px);':'background-color: #000;'?>
}
#tags .tag
{
	background-color: #000;
	box-shadow: 0px 0px 7px 0px #888;
}
}