var conn = new WebSocket(ws_address);

conn.onopen = function(e) {
	console.log("Connection established!");
	conn.send("core\tgetRooms");
};

conn.onmessage = function(e) {
	handleReturn(e.data);
};

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

function handleReturn(data) {
	var jsonData = JSON.parse(data);
	console.log(jsonData);

	switch(jsonData.type) {
		case "verified":
			if(jsonData.pass_key == "") {
				setCookie("verify_key", jsonData.key, 1);
				window.location.replace(web_root + "chat.php");
			} else {
				conn.send("core\tcheckPasswordHash\t" + jsonData.key + "\t" + Sha256.hash(Sha256.hash($("#password_field").val()) + jsonData.pass_key));
			}
			break;

		case "passwordVerified":
			setCookie("verify_key", jsonData.key, 1);
			setCookie("pass_key", jsonData.pass_key, 1);
			window.location.replace(web_root + "chat.php");
			break;

		case "ClientError":
			alert(jsonData[0]);
			$("#submitInfo").removeClass("disabled");
			$("#room_field").attr("disabled", false);
			$("#username_field").attr("disabled", false);
			$("#password_field").attr("disabled", false);
			break;

		case "checkNSFW":
			if(!jsonData.nsfw) {
				conn.send("core\tcheckDetails\t" + $("#room_field").val() + "\t" + $("#username_field").val() + "\t" + $("#password_field").val());
				return;
			}

			var agreed = confirm("This room contains NSFW content, do you wish to join?");
			if(agreed) {
				conn.send("core\tcheckDetails\t" + $("#room_field").val() + "\t" + $("#username_field").val() + "\t" + $("#password_field").val());
			} else {
				$("#submitInfo").removeClass("disabled");
				$("#room_field").attr("disabled", false);
				$("#username_field").attr("disabled", false);
				$("#password_field").attr("disabled", false);
				return;
			}
			break;

		case "roomsData":
			for(i in jsonData) {
				if(jsonData.type == jsonData[i]) {
					continue;
				}

				var element = $("<tr></tr>");
				element.append('<td class="room_name">' + jsonData[i].name + "</td>");
				element.append("<td>" + jsonData[i].users.toLocaleString() + "</td>");
				element.append("<td>" + (jsonData[i].nsfw ? "Yes" : "No") + "</td>");

				if(jsonData[i].nsfw) {
					element.css("background-color", "#FFEBEE");
				}

				$(".rooms").append(element);
			}
			break;
	}
}

if(window.location.hash != "") {
	$("#room_field").val(window.location.hash.substr(1));
}

$("#submitInfo").on("click", function(){
	if($(this).hasClass("disabled")) {
		return;
	}
	if($("#room_field").val() == "") {
		$("#room_field").addClass("bad_input");
		setTimeout(function(){
			$("#room_field").removeClass("bad_input");
		}, 500);
		return;
	}

	conn.send("core\tcheckNSFW\t" + $("#room_field").val());
	$(this).addClass("disabled");
	$("#room_field").attr("disabled", true);
	$("#username_field").attr("disabled", true);
	$("#password_field").attr("disabled", true);
});

$(".rooms").on("click", ".room_name", function(){
	$("#room_field").val($(this).text());
});