<?php
session_start();
?>

<html>
	<head>
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.2/jquery.min.js"></script>
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>
		<style>
			.jumbotron {
				text-align: center;
			}
			.inputs {
				margin-left: auto;
				margin-right: auto;
			}
			input {
				text-align: center;
			}
			.glyphicon-refresh-animate {
			    -animation: spin .7s infinite linear;
			    -webkit-animation: spin2 .7s infinite linear;
			}

			@-webkit-keyframes spin2 {
			    from { -webkit-transform: rotate(0deg);}
			    to { -webkit-transform: rotate(360deg);}
			}

			@keyframes spin {
			    from { transform: scale(1) rotate(0deg);}
			    to { transform: scale(1) rotate(360deg);}
			}
			.space {
				width: 10px;
				display: inline-block;
			}
			.glyphicon {
				display: none;
				top: 3px;
				margin-left: 10px;
			}
		</style>
		<script>
		$(document).ready(function() {
			$("#links").click(function() {
				$(".glyphicon").css("display", "inline-block");
				$(".alert").hide();
				var distance = $("#distance").val();
				var location = $("#location").val();
				$.post("api.php", {distance: distance, location: location}, function(data) {
					$(".glyphicon").hide();
					var result = JSON.parse(data);
					if(result.status != "success") {
						$(".alert").text(result.error).show();
					} else {
						var links = Object.keys(result.links);
						console.log(links);
						for(var i = 0; i < links.length; i++) {
							$("#list").append("<tr><td>" + links[i] + "</td></tr>");
						}
					}
				});
			});
		});
		</script>
	</head>
	<body class="container">
		<div class="jumbotron">
			<h1>Find News Articles</h1>
			<p class="input-group input-group-lg inputs">
				<input id="location" type="text" class="form-control" placeholder="Location" />
				<input id="distance" type="text" class="form-control" placeholder="Distance" />
			</p>
			<p id="links" class="btn btn-primary btn-lg" role="button">Get Links!<span class="glyphicon glyphicon-refresh glyphicon-refresh-animate"></span></p>
		</div>
		<div class="alert alert-danger" role="alert" style="display: none; text-align: center;"></div>
		<table class="table table-striped">
			<thead>
				<tr>
					<th>Link</th>
				</tr>
			</thead>
			<tbody id="list">
			</tbody>
		</table>
	</body>
</html>
