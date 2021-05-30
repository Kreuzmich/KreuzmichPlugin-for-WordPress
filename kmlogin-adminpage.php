<?php

/** Acknowledgment
*	This method of creating a settings page for wordpress is based on Otto's tutorial:
*	http://ottopress.com/2009/wordpress-settings-api-tutorial/
*	
*	Copied from https://codex.wordpress.org/Creating_Options_Pages
*/ 

class KreuzmichSettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'Kreuzmich Settings', 
            'Kreuzmich Plugin', 
            'manage_options', 
            'kreuzmich-settings', 
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'kreuzmich_option' );
        ?>
        <div class="wrap">
            <h1>Kreuzmich Plugin Einstellungen</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'kreuzmich_option_group' );
                do_settings_sections( 'kreuzmich-settings' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'kreuzmich_option_group', // Option group
            'kreuzmich_option', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );
		
		add_settings_section(
            'kreuzmich-plugin-settings', // ID
            '', // Title
            array( $this, 'print_section_info' ), // Callback
            'kreuzmich-settings' // Page
        );  
		
		add_settings_section(
            'kreuzmich-http-settings', // ID
            'Verbindung mit Kreuzmich', // Title
            array( $this, 'print_section2_info' ), // Callback
            'kreuzmich-settings' // Page
        );  
		
		add_settings_section(
            'kreuzmich-user-settings', // ID
            'Regeln & Fehlermeldungen', // Title
            array( $this, 'print_section3_info' ), // Callback
            'kreuzmich-settings' // Page
        );  

        add_settings_field(
            'http_user', // ID
            'HTTP Benutzername (optional)', // Title 
            array( $this, 'http_user_callback' ), // Callback
            'kreuzmich-settings', // Page
            'kreuzmich-http-settings' // Section           
        );      

		add_settings_field(
            'http_password', // ID
            'HTTP Passwort (optional)', // Title 
            array( $this, 'http_password_callback' ), // Callback
            'kreuzmich-settings', // Page
            'kreuzmich-http-settings' // Section           
        );      

		add_settings_field(
            'city', // ID
            'Stadt / Subdomäne', // Title 
            array( $this, 'city_callback' ), // Callback
            'kreuzmich-settings', // Page
            'kreuzmich-http-settings' // Section           
        );      

		add_settings_field(
            'new_users', // ID
            'Neue Benutzer zulassen',  // Title 
            array( $this, 'new_users_callback' ), // Callback
            'kreuzmich-settings', // Page
            'kreuzmich-user-settings'// Section  
        ); 
		
		add_settings_field(
			'additional_user_permissions', // ID
			'Berechtigungen bei jedem Login mit weiterer Quelle abgleichen', // Title
			array( $this, 'additional_user_permissions_callback' ), // Callback
			'kreuzmich-settings', // Page
            'kreuzmich-user-settings'// Section  
        ); 
		
        add_settings_field(
            'error_message', // ID
            'Fehlermeldung bei fehlerhaftem Login',  // Title 
            array( $this, 'error_message_callback' ),  // Callback
            'kreuzmich-settings', // Page
            'kreuzmich-user-settings'// Section  
        );  
		
		add_settings_field(
            'expired_users', // ID
            'Abgelaufene Kreuzmich Benutzer können sich einloggen',  // Title 
            array( $this, 'expired_users_callback' ), // Callback
            'kreuzmich-settings', // Page
            'kreuzmich-user-settings'// Section  
        ); 
		
		add_settings_field(
            'error_message_expired', // ID
            'Fehlermeldung bei abgelaufenem Konto',  // Title 
            array( $this, 'error_message_expired_callback' ),  // Callback
            'kreuzmich-settings', // Page
            'kreuzmich-user-settings'// Section  
        ); 
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();		
		if( isset( $input['http_user'] ) )
            $new_input['http_user'] = sanitize_text_field( $input['http_user'] );
		
		if( isset( $input['http_password'] ) )
            $new_input['http_password'] = sanitize_text_field( $input['http_password'] );
		
		if( isset( $input['city'] ) )
            $new_input['city'] = sanitize_text_field( $input['city'] );
		
		if( isset( $input['expired_users'] ) )
			$new_input['expired_users'] = sanitize_text_field( $input['expired_users'] );

		if( isset( $input['new_users'] ) )
			$new_input['new_users'] = sanitize_text_field( $input['new_users'] );
		
        if( isset( $input['error_message'] ) )
			$new_input['error_message'] = $input['error_message'];

        if( isset( $input['error_message_expired'] ) )
			$new_input['error_message_expired'] = $input['error_message_expired'];	

        if( isset( $input['additional_user_permissions'] ) )
			$new_input['additional_user_permissions'] = $input['additional_user_permissions'];		
		
        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Hier kannst du einige Einstellungen für das Kreuzmich Plugin vornehmen:';

    }

    public function print_section2_info()
    {
        print 'Wenn nicht explizit gefordert, müssen HTTP Benutzername und HTTP Passwort leer bleiben.';

    }
	
    public function print_section3_info()
    {
        print 'Hier kannst du festlegen, wer sich einloggen darf und wie die Fehlermeldungen lauten.<br>Du kannst in den folgenden Texten ein kleines Kreuzmich Logo mittels &lt;span id="km"&gt;&lt;/span&gt; einbauen.<br> Andere HTML-Tags sind auch erlaubt.';

    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function http_user_callback()
    {
		printf(
            '<input type="text" id="http_user" name="kreuzmich_option[http_user]" value="%s" />',
            isset( $this->options['http_user'] ) ? esc_attr( trim($this->options['http_user'])) : ''
        );
    }
	
	/** 
     * Get the settings option array and print one of its values
     */
    public function http_password_callback()
    {
        printf(
            '<input type="text" id="http_password" name="kreuzmich_option[http_password]" value="%s" />',
            isset( $this->options['http_password'] ) ? esc_attr( trim($this->options['http_password'])) : ''
        );
		
    }
	
	/** 
     * Get the settings option array and print one of its values
     */
    public function city_callback()
    {
        printf(
            '<input type="text" id="city" name="kreuzmich_option[city]" value="%s" />',
            isset( $this->options['city'] ) ? esc_attr( trim($this->options['city'])) : ''
        );
		
		printf(
			'<p>Der Stadtname in der Kreuzmich-Adresse,<br>z.B. "duesseldorf" für https://duesseldorf.kreuzmich.de</p>'
		);
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function expired_users_callback()
    {
        printf(
            '<input type="checkbox" id="expired_users" name="kreuzmich_option[expired_users]" ' . 
            (($this->options['expired_users']) ? 'checked' : '')  . ' />'
        );
    }

	/** 
     * Get the settings option array and print one of its values
     */
    public function error_message_callback()
    {
		$default_message = '<strong>Fehler! </strong>Kein <span id="km"></span> Konto gefunden.<br>Entweder hast du Benutzername oder Passwort falsch eingegeben oder du bist noch nicht freigeschaltet.<br>Prüfe dies durch Login auf <span id="km"></span>. In der Regel dauert eine Freischaltung ein paar Tage. Erstsemester werden erst zum Vorlesungsbeginn freigeschaltet.';
		printf(
            '<textarea id="error_message" name="kreuzmich_option[error_message]" rows="6" cols="50">%s</textarea>',
            (isset( $this->options['error_message'] ) ? esc_textarea(  $this->options['error_message']) : $default_message)
        );
		printf(
			'<p>Diese Fehlermeldung erscheint bei unbekanntem Kreuzmich Benutzernamen,<br>falschem Passwort, oder noch nicht freigeschaltetem Kreuzmich Konto.</p>'
		);
    }
	
	/** 
     * Get the settings option array and print one of its values
     */
    public function error_message_expired_callback()
    {
		$default_message = '<strong>Fehler!</strong><br>Dein <span id="km"></span>Konto hat die Laufzeit von 13 Semestern überschritten & ist <strong>abgelaufen</strong>.<br>Versuche dich bei <span id="km"></span> einzuloggen oder nutze die <span id="km"></span>Hilfeseite, um herauszufinden, wie du es verlängern kannst.';
		printf(
            '<textarea id="error_message_expired" name="kreuzmich_option[error_message_expired]" rows="6" cols="50">%s</textarea>',
            (isset( $this->options['error_message_expired'] ) ? esc_textarea(  $this->options['error_message_expired']) : $default_message)
        );
		printf(
			'<p>Falls abgelaufene Kreuzmich Nutzer sich nicht einloggen dürfen,<br>wird ihnen bei Loginversuch diese Fehlermeldung angezeigt.</p>'
		);
    }
	
	/** 
     * Get the settings option array and print one of its values
     */
    public function new_users_callback()
    {
        printf(
            '<input type="checkbox" id="new_users" name="kreuzmich_option[new_users]" ' . 
            (($this->options['new_users']) ? 'checked' : '')  . ' />'
        );
		printf(
			'<p>Benutzer zulassen, die von Kreuzmich bestätigt wurden, aber bisher noch nie auf der Homepage waren.<br>Für diese Benutzer wird ein neues Benutzerkonto auf der Homepage angelegt.</p>'
		);
    }
	
	/** 
     * Get the settings option array and print one of its values
     */
    public function additional_user_permissions_callback()
    {
        printf(
            '<input type="checkbox" id="additional_user_permissions" name="kreuzmich_option[additional_user_permissions]" ' . 
            (($this->options['additional_user_permissions']) ? 'checked' : '')  . ' />'
        );
		
		printf(
			'<p>Ist diese Option aktiv, wird bei jedem(!) Login eine weitere Quelle abgefragt, und die Rechte des einloggenden Benutzers werden entsprechend der externen Quelle auch auf der Homepage angepasst. Man wird also z.B. zum Administrator, Fachschaftler, FSR-ler hochgestuft oder degradiert. Wer sich längere Zeit hier nicht eingeloggt hat, bleibt also vorerst auf seinem alten Rang, bis er/sie sich erneut einloggt. All dies geschieht erst nach erfolgreichem Abgleich mit Kreuzmich. <b>Die entsprechende PHP-Funktion get_additional_user_permissions in kmlogin.php muss hierfür definiert sein. Diese Option kann die Loginzeit für alle verlängern.</b></p>'
		);
    }
	
}

if( is_admin() )
    $my_kreuzmich_page = new KreuzmichSettingsPage();

// 		print '<p>Du kannst in den folgenden Texten ein kleines Kreuzmich Logo mittels &lt;span id="km"&gt;&lt;/span&gt; in den Text einbauen.</p>';
// 		print '<p>Wenn nicht explizit gefordert, müssen HTTP Benutzername und HTTP Passwort leer bleiben.</p>';
