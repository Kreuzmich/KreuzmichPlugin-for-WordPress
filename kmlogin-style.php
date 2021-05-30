<?php

add_action( 'login_enqueue_scripts', 'login_km_logo' );

function login_km_logo() { ?>
    <style type="text/css">
		#km {
			background: url('/wp-content/themes/fluida-fsmed-child/includes/Kreuzmich_sprite.png') 0px 0px;
			background-size:cover;
			width: 20px;
			height: 18px;
			display:inline-block;
			position: relative;
			top: 3px;
		}
    </style>
<?php }


?>