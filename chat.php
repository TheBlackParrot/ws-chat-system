<html>

<head>
	<link rel="stylesheet" type="text/css" href="css/reset.css"/>
	<link rel="stylesheet" type="text/css" href="css/main.css"/>

	<title>ChatItWS</title>

	<script src="js/jquery.js"></script>
	<script src="js/config.js"></script>
	<script src="js/sha256.js"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/twemoji/1.3.2/twemoji.min.js"></script>
</head>

<body>
	<div class="wrapper">
		<div class="chat_area">
			<div class="more_wrapper">
				More messages below...
			</div>
		</div>
		<div class="user_list">
		</div>
		<div class="input_wrapper">
			<input type="textbox" class="input_field" placeholder="Send a message"/>
			<div class="char_limit">2,048</div>
			<div class="button" id="submitMsgButton">Submit</div>
		</div>
	</div>
	<script src="js/chat.js"></script>
</body>

</html>