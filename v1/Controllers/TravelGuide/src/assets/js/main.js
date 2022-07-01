const debug = true;
function onLoad()
{
	$('#tags .tag.word').click(function(evt){
		$('#tags .tag.word').removeClass('active');
		$(this).addClass('active');

		var left = $('#tags').scrollLeft();
		var offset = $(this).offset().left;

		$('#tags').animate({
			scrollLeft: left+offset-20
		}, 300);

		let name = $(this).text();
		if ($(this).hasClass('all'))
			name = null;

		let data = {tag: name};

		if (coordX && coordY)
		{
			data.x = coordX;
			data.y = coordY;
		}

		GetData('./get/list', data, function(res){
			SetList(res);
		});
	});

	$('#tags .tag.back').click((evt) => {
		/*$('#tags .tag.word').css('display', 'inline-block');
		$('#tags .tag.back').hide();
		$('#tags .tag.name').hide();
		$('#tags .tag.name').text('');*/
	});

	$('#travel_guide_box').on('click', '.item', function(evt){
		let itemId = $(this).data('id');
		if (/android/i.test(navigator.userAgent||navigator.vendor||window.opera))
			window.history.pushState({}, '', window.location.href+'#itemId='+itemId);
		GetData('./get/item', {tg_id: itemId}, function(res){
			SetItem(res);
		});
	});
}

window.onhashchange = function(evt) {
	if (/android/i.test(navigator.userAgent||navigator.vendor||window.opera))
		CloseItem();
};

var localCache = {
	data: {},
	remove: function(url) {
		delete localCache.data[url];
	},
	exist: function(url) {
		return localCache.data.hasOwnProperty(url) && localCache.data[url] !== null;
	},
	get: function(url) {
		if (debug) console.log('Getting in cache for url: ' + url);
		return localCache.data[url];
	},
	set: function(url, cachedData, callBack) {
		localCache.remove(url);
		localCache.data[url] = cachedData;
		if (typeof callBack === 'function')
			callBack(cachedData);
	}
};

let Loading = {
	show: function() {
		$('body').addClass('load');
		$('#load_layer').fadeIn();
		$('#load_layer').html('<div class="loader-rnd"><div></div><div></div><div></div><div></div></div>');
	},
	close: function() {
		$('body').removeClass('load');
		$('#load_layer').fadeOut(function(){
			$('#load_layer').html('');
		});
	}
};

function GetData(url, params, callBack)
{
	let urlCacheKey = url+'_#_'+JSON.stringify(params);
	$.ajax({
		url: url,
		type: 'POST',
		cache: true,
		data: params,
		beforeSend: function (request) {
			request.setRequestHeader('Auth', userToken);
			if (localCache.exist(urlCacheKey)) {
				if (typeof callBack === 'function')
					callBack(localCache.get(urlCacheKey));
				return false;
			}
			Loading.show();
			return true;
		},
		complete: function (jqXHR, textStatus) {
			Loading.close();
			if (jqXHR.readyState === 4)
				localCache.set(urlCacheKey, jqXHR.responseJSON.data, callBack);
		}
	});
}

function SetList(data)
{
	$('#travel_guide_box').html();
	let body = '';

	data.forEach((v,i) => {
		body += '<div class="item'+((v.prime)?' big':'')+'" data-id="'+v.id+'">\
			<div class="img-preview">\
				<img src="'+v.image_preview+'">\
			</div>\
			<div class="title">'+v.title+'</div>\
			<div class="short-text">'+v.short_text+'</div>\
		</div>';
	});

	$('#travel_guide_box').html(body);
}

function SetItem(data)
{
	let body = '';

	body = '<div class="image_preview" onClick="CloseItem()">\
		<img src="'+data.image_preview+'"/>\
		<div class="text-wrapper">\
			<div class="text">&#10094; '+data.title+'</div>\
		</div>\
	</div>';

	body += '<div class="content">'+data.body+'</div>';

	$('#content_item').html(body);

	$('#travel_guide_box').css({position: 'fixed'});

	$('#travel_guide_box').animate({
		left: '-100%'
	}, 300);

	$('#header').animate({
		top: '-25%'
	}, 300);

	$('#content_item').animate({
		left: '0'
	}, 300);
}

function CloseItem()
{
	$('#travel_guide_box').css({position: 'relative'});
	$('#travel_guide_box').animate({
		left: '0'
	}, 300);

	$('#header').animate({
		top: '0'
	}, 300);

	$('#content_item').animate({left: '100%'}, 300, function(){
		$('#content_item').html('');
	});
}