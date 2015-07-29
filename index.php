<html>

<head>
	<link rel="stylesheet" type="text/css" href="css/reset.css"/>
	<link rel="stylesheet" type="text/css" href="css/lobby.css"/>

	<title>ChatItWS</title>

	<script src="js/jquery.js"></script>
	<script src="js/config.js"></script>
	<script src="js/sha256.js"></script>
</head>

<body>
	<div class="wrapper">
		<div class="header">
			ChatItWS
		</div>
		<div class="content">
			<div class="inputs">
				Username<br/>
				<input type="textbox" id="username_field" placeholder="Username"/>
				Password (if registered)<br/>
				<input type="password" id="password_field" placeholder="Password (if registered)"/>
				Room<br/>
				<input type="textbox" id="room_field" placeholder="Room"/>
				<div class="button" id="submitInfo">Join Room</div>
			</div>
			<hr/>
			<div class="rooms_wrapper">
				<table class="rooms">
					<tr>
						<td>Room</td>
						<td>Users</td>
						<td>NSFW</td>
					</tr>
				</table>
			</div>
			<hr/>
			<div class="info">
				ChatItWS is not responsible for any material posted in these rooms, nor does ChatItWS endorse any of said material. Rooms with mature content are marked as <strong>N</strong>ot <strong>S</strong>afe <strong>F</strong>or <strong>W</strong>ork, these rooms may only be entered by those 18 years of age and older.<br/>
				 Rooms maintain their own set of rules, however there are some global rules all rooms must follow:
				<ul>
					<li>Do not jeopardize the health and safety of other users, nor encourage anything that may do so.</li>
					<li>Do not post any personal information of someone else that relates back to them, real world or not. Doxxing falls into this rule, as is posting any information from a dox.</li>
					<li>Do not post any mature/suggestive/NSFW content relating to minors under the age of 18 years old</li>
				</ul>
				You are responsible for the content you post here. ChatItWS reserves the right to log and track what you post, and remove you from this service at any time and for any reason.<br/><br/>
				<strong>By using this service, you agree to these terms.</strong>
				<hr/>
				<strong>ChatItWS</strong> (may be temporary) is a chat service that runs on PHP and Websockets via <a href="http://socketo.me/">Ratchet</a>.<br/>
				Features include:
				<ul>
					<li>Inline Markdown parsing
						<ul>
							<li>(some syntax has been removed or modified to prevent abuse)</li>
						</ul>
					</li>
					<li>Auto-wrapping of audio and video links to HTML5 elements</li>
					<li>Auto-wrapping of images to &lt;img&gt; elements</li>
					<li>Supports embedding from the following services (so far):
						<ul>
							<li>YouTube</li>
							<li>SoundCloud</li>
							<li>Vine</li>
							<li>Imgur GIFV</li>
							<li>Pastebin</li>
						</ul>
					</li>
					<li>Global Twitch.tv emoticons (via <a href="https://twitchemotes.com/apidocs">Twitch Emotes</a>)</li>
					<li>ASCII emoticons (via <a href="https://github.com/twitter/twemoji">Twemoji</a>)</li>
				</ul>
			</div>
		</div>
	</div>
	<script src="js/lobby.js"></script>
</body>