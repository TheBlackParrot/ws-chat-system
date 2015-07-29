var conn = new WebSocket(ws_address);

conn.onopen = function(e) {
	console.log("Connection established!");
	conn.send("core\tpreventChromeFromBeingStupid");
};

conn.onclose = function(e) {
	var jsonData = {}; // dead serious
	jsonData.is_sys = 1;
	jsonData.msg = 'Disconnected from the server, click <a href="' + web_root + '">here</a> to go back.';
	$(".chat_area").append(getMessageElement(jsonData, 0));
}

conn.onmessage = function(e) {
	handleReturn(e.data);
};

// shoutouts to w3schools
function getCookie(cname) {
	var name = cname + "=";
	var ca = document.cookie.split(';');
	for(var i=0; i<ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1);
		if (c.indexOf(name) == 0) return c.substring(name.length,c.length);
	}
	return "";
}
function setCookie(cname, cvalue, exdays) {
	var d = new Date();
	d.setTime(d.getTime() + (exdays*24*60*60*1000));
	var expires = "expires="+d.toUTCString();
	document.cookie = cname + "=" + cvalue + "; " + expires + "path=/";
}

function getCurrentTime() {
	var d = new Date();
	var hours = d.getHours();
	var minutes = d.getMinutes();
	var seconds = d.getSeconds();

	return (hours < 10 ? '0' : '') + hours + ':' + (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
}

function getFormattedDateTime(timestamp) {
	var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
	var d = new Date(timestamp*1000);

	var month = months[d.getMonth()];
	var day = d.getDate();
	var year = d.getFullYear();

	var hours = d.getHours();
	var minutes = d.getMinutes();
	var seconds = d.getSeconds();

	var str = month + " " + day + ", " + year + " at ";
	str += (hours < 10 ? '0' : '') + hours + ':' + (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;

	return str;
}

function sortRank(a, b) {
	var a_classes = a.className.split(" ");
	var b_classes = b.className.split(" ");
	if(a_classes[1] == "rank_owner") {
		//abc
		a_classes[1] = "rank_aowner";
	}
	if(b_classes[1] == "rank_owner") {
		//abc
		b_classes[1] = "rank_aowner";
	}
	return a_classes[1] > b_classes[1];
}

// this name will be changed
String.prototype.parseTwitch = function() {
	var words = this.split(" ");
	for(i in words) {
		if(!$.isEmptyObject(twitch_emotes)) {
			if(words[i] in twitch_emotes.emotes) {
				var url = twitch_emotes.template;
				url = url.replace('{image_id}', twitch_emotes.emotes[words[i]].image_id);
				words[i] = '<img class="twitch_emote" src="' + url + '"/>';
			}
		} else {
			console.log("Twitch emotes failed to load :(");
		}
		if(words[i].substr(0, 3) == "/r/") {
			var subreddit = words[i].substr(3);
			if(subreddit.replace(/[A-Z]|[0-9]|\-|\_/gi, "") == "") {
				words[i] = '<a href="https://reddit.com/r/' + subreddit + '">/r/' + subreddit + '</a>';
			}
		}
	}
	return words.join(" ");
}


var user_data;
var twitch_emotes = {};
var final_timestamp;

function handleReturn(data) {
	var jsonData = JSON.parse(data);
	console.log(jsonData);

	if(typeof jsonData.name !== "undefined" && typeof user_data !== "undefined") {
		if(user_data.name == jsonData.name) {
			final_timestamp = Date.now();
			console.log("Delay: " + (final_timestamp - init_timestamp) + "ms");
		}
	}

	switch(jsonData.type) {
		case "user_data":
			user_data = jsonData;
			document.title = "ChatItWS - " + user_data.room;
			setCookie("verify_key", "", 1);
			conn.send("room\t" + user_data.room + "\t" + user_data.uuid + "\t" + user_data.verification + "\tgetUsers");
			break;

		case "chatMessage":
			var element = getMessageElement(jsonData, 0);
			$(".chat_area").append(element);
			
			var ytwrapper = $(".chat_area > .message:last-child > .text > .youtube-wrapper");
			if(typeof ytwrapper[0] !== "undefined") {
				ytwrapper.height(ytwrapper.width() * 0.5625);
			}
			
			var vineframe = $(".chat_area > .message:last-child > .text > .vine-embed");
			if(typeof vineframe[0] !== "undefined") {
				vineframe.height(vineframe.width());
			}

			// it'd be great if chrome properly waited for elements to completely render
			// y'know
			// so i can properly scroll down

			/*
			element[0].onload = function(e){
				console.log("trigger");
				console.log($(".chat_area > .message:last-child").height());
				console.log($(".chat_area > .message:last-child").children(".text").height());
				scrollUser(element.height());
			};
			*/

			scrollUser(element.height());
			break;

		case "chatCommand":
			switch(jsonData.command) {
				case "me":
					var element = getMessageElement(jsonData, 0);
					element.children(".text").css("color", jsonData.color);
					element.children(".text").css("font-style", "italic");
					element.children(".user").addClass("mecmd").css("font-style", "italic");

					$(".chat_area").append(element);
					scrollUser(element.height());
					break;

				case "listcolors":
					var element = getMessageElement(jsonData, 0);
					element.children(".text").css("opacity", "1");

					$('.chat_area').append(element);
					scrollUser(element.height());
					break;

				case "help":
					var element = getMessageElement(jsonData, 0);
					$(".chat_area").append(element);
					scrollUser(element.height());
					break;

				case "mod":
					var element = getMessageElement(jsonData, 0);
					$(".chat_area").append(element);
					scrollUser(element.height());
					$('.user_line[uuid="' + jsonData.uuid + '"]').addClass("rank_mod");
					$('.user_line[uuid="' + jsonData.uuid + '"]').removeClass("rank_user");
					break;

				case "demod":
					var element = getMessageElement(jsonData, 0);
					$(".chat_area").append(element);
					scrollUser(element.height());
					$('.user_line[uuid="' + jsonData.uuid + '"]').removeClass("rank_mod");
					$('.user_line[uuid="' + jsonData.uuid + '"]').addClass("rank_user");
					break;

				case "motd":
					if(jsonData.msg == null) {
						return;
					}
					var element = getMessageElement(jsonData, 1);
					$(".chat_area").append(element);
					scrollUser(element.height());
					break;

				case "ban":
					jsonData.msg += " The ban is set to expire on " + getFormattedDateTime(jsonData.ends) + ".";
					var element = getMessageElement(jsonData, 1);
					$(".chat_area").append(element);
					scrollUser(element.height());
					break;

				case "trash":
					jsonData.msg = jsonData.name + "'s messages were cleared from the room.";
					$('.message[id="' + jsonData.uuid + '"]').each(function(){
						$(this).remove();
					})
					var element = getMessageElement(jsonData, 1);
					$(".chat_area").append(element);
					scrollUser(element.height());
					break;

				case "listbans":
					jsonData.msg = "";
					for(i in jsonData.IP) {
						jsonData.msg += (i+1) + ". " + jsonData.username[i] + " (" + jsonData.IP[i] + "): expires " + getFormattedDateTime(jsonData.ends[i]) + "<br/>";
					}
					var element = getMessageElement(jsonData, 1);
					$(".chat_area").append(element);
					scrollUser(element.height());
					break;

				case "remove":
					var height = $('.message[msg_uuid="' + jsonData.msg_uuid + '"]').height();
					$('.message[msg_uuid="' + jsonData.msg_uuid + '"]').remove();
					scrollUser(height);
					newMessages--;
					break;
			}
			break;

		case "userList_data":
			$(".user_list").append(getUserListElement(jsonData));
			var elem = $('.user_list').find('.user_line').sort(sortRank);

			$('.user_list').append(elem);
			break;

		case "userLeave":
			if(parseInt(jsonData.silent) != 1) {
				jsonData.msg = "left the room: " + jsonData.reason;
				var element = getMessageElement(jsonData, 1);
				$(".chat_area").append(element);
				scrollUser(element.height());
			}
			$('.user_line[uuid="' + jsonData.uuid + '"]').remove();
			break;

		case "userJoined":
			jsonData.msg = "joined the room";
			jsonData.color = jsonData.bg_color;

			var element = getMessageElement(jsonData, 1);
			$(".chat_area").append(element);
			scrollUser(element.height());
			break;

		case "updateColor":
			$('.user_line[uuid="' + jsonData.uuid + '"]').css("background-color", jsonData.bg_color);
			$('.user_line[uuid="' + jsonData.uuid + '"]').css("color", jsonData.fg_color);
			break;


		case "userActuallyOpenedThePage":
			if(parseInt(jsonData.ping) == 1) {
				var verify_key = getCookie("verify_key");
				var pass_key = getCookie("pass_key");
				conn.send("core\trequestUUID\t" + verify_key + "\t" + pass_key);

				$.get("https://twitchemotes.com/api_cache/v2/global.json", function(data) {
					twitch_emotes.emotes = data.emotes;
					twitch_emotes.template = data.template.small;
				}, "json");
			}
			break;

		case "ClientError":
			jsonData.msg = '<strong style="color: #f00;">Error: </strong>' + jsonData[0];
			var element = getMessageElement(jsonData, 1);
			$(".chat_area").append(element);
			element.ready(function(){
				scrollUser(element.height());
			});
			break;

		case "banMessage":
			jsonData.name = "system";
			jsonData.msg += " The ban is set to expire on " + getFormattedDateTime(jsonData.ends) + ".";
			var element = getMessageElement(jsonData, 1);
			$(".chat_area").append(element);
			scrollUser(element.height());
			break;
	}
}

function getMessageElement(data, is_sys) {
	var uuid_str = "";
	var msg_uuid_str = "";
	if(typeof data.uuid !== "undefined") {
		uuid_str = ' id="' + data.uuid + '"';
	}
	if(typeof data.msg_uuid !== "undefined") {
		msg_uuid_str = ' msg_uuid="' + data.msg_uuid + '"';
	}

	if(typeof data.name === "undefined") {
		data.name = "system";
	}

	var element = $('<div class="message"' + uuid_str + msg_uuid_str + '></div>');
	if(parseInt(data.is_sys) == 1 || is_sys == 1) {
		element.addClass("msg_sys");
	}
	if(typeof data.pm !== "undefined") {
		element.addClass("msg_pm");
	}

	if(data.color == "") {
		color = "#000";
	} else {
		color = data.color;
	}

	element.append('<span class="timestamp">' + getCurrentTime() + '</span>');
	if(typeof data.pm !== "undefined") {
		element.append('<span class="user" style="color: ' + color + ';">' + data.sender + ' --> ' + data.recipient + '</span>');
	} else {
		element.append('<span class="user" style="color: ' + color + ';">' + data.name + '</span>');
	}
	element.append('<span class="text">' + twemoji.parse(data.msg).parseTwitch() + '</span>');

	return element;
}

$(window).on("resize", function(){
	$(".youtube-wrapper").each(function(){
		$(this).height($(this).width() * 0.5625);
	});
	$(".vine-embed").each(function(){
		$(this).height($(this).width());
	});
});

var init_timestamp;

function sendMessage(msg) {
	if(msg.substr(0, 10) == "/register ") {
		var args = msg.substr(10);
		var args = args.split(" ");
		if(args[0] != args[1]) {
			var data = {};
			data.msg = "The passwords do not match, please try again.";
			$(".chat_area").append(getMessageElement(data, 1));
			return;
		}

		msg = "/register " + Sha256.hash(args[0]);
	}

	if(msg.substr(0, 6) == "/clear") {
		$(".chat_area").empty();
		return;
	}

	var str = "room";
	str += "\t" + user_data.room;
	str += "\t" + user_data.uuid;
	str += "\t" + user_data.verification;
	str += "\tsendMsg" ;
	str += "\t" + msg;

	$(".char_limit").css("color", "#999");
	$(".char_limit").text("2,048");

	init_timestamp = Date.now();
	conn.send(str);
}

function getUserListElement(data) {
	var element = $('<div class="user_line rank_' + data.rank + '" uuid="' + data.uuid + '">' + data.name + '</div>');
	element.css("background-color", data.bg_color);
	element.css("color", data.fg_color);
	if(data.uuid == user_data.uuid) {
		element.css("font-weight", "700");
	}

	return element;
}

function scrollUser(height) {
	var element = $(".chat_area");

	if($(".chat_area > .message").length >= 200) {
		var element = $(".chat_area");
		var height = $(".chat_area > .message:first").outerHeight();

		if((element[0].scrollHeight - element[0].scrollTop) != element.height()) {
			$(".chat_area")[0].scrollTop -= height;
		}
		
		// this will be a setting eventually

		if($(".chat_area > .message:first").hasClass("not_seen")) {
			newMessages--;
		}
		$(".chat_area > .message:first").remove();
	}

	var is_active = 1;
	if(typeof $(window).data("prevType") !== "undefined") {
		if($(window).data("prevType") == "blur") {
			is_active = 0;
		}
	}
	if((element[0].scrollHeight - element.scrollTop()) - element.height() <= (70 + height)) {
		if(is_active) {
			element.scrollTop(100000);
			$(".more_wrapper").hide();
		} else {
			if($(".chat_area").height() != $(".chat_area")[0].scrollHeight) {
				$(".more_wrapper").width(element[0].scrollWidth);
				$(".more_wrapper").show();
				newMessageText();
				$(".chat_area > .message:last").addClass("not_seen");
			}
		}
	} else {
		if($(".chat_area").height() != $(".chat_area")[0].scrollHeight) {
			$(".more_wrapper").width(element[0].scrollWidth);
			$(".more_wrapper").show();
			newMessageText();
			$(".chat_area > .message:last").addClass("not_seen");
		}
	}
}

function isScrolledIntoView(elem) {
    var $elem = $(elem);
    var $window = $(window);

    var docViewTop = $window.scrollTop();
    var docViewBottom = docViewTop + $window.height();

    var elemTop = $elem.offset().top;
    var elemBottom = elemTop + $elem.height();

    return ((elemBottom <= docViewBottom) && (elemTop >= docViewTop));
}

var newMessages = 0;
function newMessageText(override) {
	var element = $(".chat_area");

	if($(".chat_area").height() == $(".chat_area")[0].scrollHeight) {
		newMessages = 0;
		return;
	}

	if(typeof override === "undefined") {
		override = 0;
	}

	if(!override){
		if((element[0].scrollHeight - element[0].scrollTop) != element.height()) {
			newMessages++;
		} else {
			newMessages = 0;
		}
	}

	if(newMessages > 0) {
		document.title = "ChatItWS - " + user_data.room + " (" + newMessages + ")";
		$(".more_wrapper").text(newMessages + " more messages below...");
	} else {
		document.title = "ChatItWS - " + user_data.room;
	}
}

$(window).on("blur focus", function(e) {
    var prevType = $(this).data("prevType");

    if (prevType != e.type) {   //  reduce double fire issues
        switch (e.type) {
            case "blur":
                console.log("Blurred");
                break;
            case "focus":
                console.log("Focused");
                break;
        }
    }

    $(this).data("prevType", e.type);
});

$('.chat_area').on('scroll', function() {
	if(typeof $(".chat_area > .not_seen:first")[0] !== "undefined") {
		$(".chat_area > .not_seen").each(function(){
			if(isScrolledIntoView($(this)[0])) {
				newMessages--;
				newMessageText(1);
				$(this).removeClass("not_seen");
			}
		});
	}

	if($(this).scrollTop() + $(this).innerHeight() >= this.scrollHeight) {
		$(".more_wrapper").hide();
		newMessages = 0;
		newMessageText(1);
	}
});

var max_characters = 2048;
$('.input_field').keypress(function(e) {
	if(e.which == 13) {
		e.preventDefault();
		
		var msg = $(this).val();
		$(this).val("").change();
		max_characters = 2048;

		sendMessage(msg);
	} else {
		updateNumChars(1);
	}
});
$('.input_field').keydown(function(e){
	if(e.which == 8) {
		updateNumChars();
	}
});
$('.input_field').keyup(function(e){
	if(e.which == 8) {
		updateNumChars();
	}
});
$('.input_field').change(function(){
	updateNumChars();
});

function updateNumChars(fix) {
	var element = $(".char_limit");
	if(typeof fix !== "undefined") {
		max_characters = (2047 - $(".input_field").val().length);
	} else {
		max_characters = (2048 - $(".input_field").val().length);
	}

	element.text(max_characters.toLocaleString());
	if(max_characters < 0) {
		element.css("color", "#d99");
	} else {
		element.css("color", "#999");
	}
}

$('#submitMsgButton').on("click", function(){
	var msg = $('.input_field').val();
	$('.input_field').val("");

	sendMessage(msg);
});

$("body").on("click", ".user_line", function(){
	$(".input_field").val($(".input_field").val() + " " + $(this).text()).change();
});

$(window).unload(function(){
	conn.send("room\t" + user_data.room + "\t" + user_data.uuid + "\t" + user_data.verification + "\tleave");
});