<?php
/*
Plugin Name: Kreuzmich Plugin
Plugin URI: http://www.bahmanafzali.de/kreuzmich-login
Description: This plugin implements user authentication login through the kreuzmich-login-api. Users who are logging in for the first time are added to the wordpress database.
Author: Raphael Menke, Bahman Afzali
Version: 1.18f
Author URI: http://www.bahmanafzali.de
*/   
   
/*  Copyright 2007-2018

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation; either version 3 of the License.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU Lesser General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

 * Acknowledgment
This Plugin is based on the previous work of Ben Lobaugh
you can find it here: http://ben.lobaugh.net/blog/7175/wordpress-replace-built-in-user-authentication
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// Lade weitere PHP Dateien für Dashboard Menue Einstellungen & Verhinderung der WordPress-eigenen Passwort-vergessen Funktion (ausser Admin)
$dir = plugin_dir_path( __FILE__ );
require_once( $dir . 'kmlogin-adminpage.php');
include( $dir . 'kmlogin-passwordreset.php');
include( $dir . 'kmlogin-style.php');



// Beginne Plugin

class Kreuzmich_Authentification
{
	public $km_options; 	 	//	array	die gespeicherten Eingaben in den Plugin-Einstellungen
	
	public $km_url; 			//	string	HTTPS Adresse zu Kreuzmich, laut Plugin-Einstellungen
	
	public $username_roh;		//  string	Username aus Loginfeld, ggf. mit Umlauten, nicht von WordPress bereinigt
	
	public function __construct()
	{
		$this->km_options = get_option( 'kreuzmich_option' ); 	//Lade gespeicherte Einstellungen
		(!empty($this->km_options['city']) ) ?: add_action( 'admin_notices', array( $this, 'km_admin_notice__error' )); //Warnmeldung falls Kreuzmich Stadt nicht gesetzt	
		$this->km_url = "https://" . $this->km_options['city'] . ".kreuzmich.de";  //globale kreuzmich-URL mit Stadt aus Einstellungen
		add_filter( 'sanitize_user', array( $this, 'wordpress_save_umlaut_username'), 10, 3  ); //Speichere Namen vor WP_sanitize_user
		add_filter( 'authenticate', array( $this, 'km_auth' ), 10, 3  ); //Leite Login um
		add_filter( 'init', array( $this, 'registration_page_redirect_to_kreuzmich' )); //Leite Registrierung um
		add_filter( 'init', array( $this, 'lostpassword_page_redirect_to_kreuzmich' )); //Leite PW vergessen um
		add_filter( 'login_message', array($this, 'km_privacy_message') ); //Datenschutz-Hinweis zur Übertragung von Daten von Kreuzmich zu WordPress
	}
	

	// Zeige Warnmeldung in WordPress Dashboard, wenn Stadt in den Plugin-Einstellungen nicht gesetzt
	public function km_admin_notice__error() {
		
		$class = 'notice notice-error';
		$message = 'Achtung! Deine Kreuzmich Stadt wurde noch nicht gesetzt. Logge dich auf keinen Fall aus! Gib erst unter Einstellungen -> Kreuzmich Plugin deine Kreuzmich Subdomäne an.';

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
	}
	
	/*
	 * neue Auth Funktion km_auth, gleiche Benutzername/Passwort mit Kreuzmich ab
	 * 
	 * 1. baue cURL and verbinde zu kreuzmich extAuth API
	 * 2. erhalte von Kreuzmich: 
	 *   JSON = array ( 
	 * 		[success]	boolean		auth erfolgreich? j/n
	 * 		[code]		integer		HTML code
	 * 		[reason]	string		code erklaert
	 * 		[detail]	string		ungenutzt
	 * 		[user]		array ( 			Benutzerdaten aus Kreuzmich
	 *			[email]			string		Email 
	 *			[firstname]		string		Vorname 
	 *			[lastname]		string		Nachname 
	 *			[expired]		boolean 	Konto abgelaufen? j/n
	 *		)
	 *	 )
	 * 3. finde bestehenden user oder erstelle neuen
	 * 4. user einloggen oder Fehlermeldung zurückgeben
	 *
	 * param	$username				string						aus Loginfeld, schon von WordPress um Umlaute bereinigt
	 * param	$password				string						aus Loginfeld, Klartext!
	 * return	$user 					WP_User oder WP_Error		Benutzer der eingeloggt wird oder Fehlermeldung
	 */
	public function km_auth( $user, $username, $password )	{
		
		// Gehe sicher dass username und password nicht leer uebergeben werden
		// Hier return null, und damit keine Fehlermeldung.
		// da sonst immer Fehlermeldung, auch wenn Seite gerade aufgerufen wird
		if (($username == '') || ($password == '')) return; 

		/*
		* Umlaute im Usernamen? Sofort Abbruch, keine cURL zu Kreuzmich
		*	
		* lade hier globale Variable $username_roh aus Funktion wordpress_save_umlaut_username
		*
		* Da die WordPress-eigene Auth Methode hier noch aktiv ist, wird der WP Error überschrieben
		* add_filter('login_errors') kann wiederum diesen Fehler überschreiben und so unsere Nachricht ausgeben
		*/
		if  (preg_match('/[äÄöÖüÜß]/', $this->username_roh)) {
			add_filter( 'login_errors', array($this, 'km_umlaut_error')); //eigene Login Fehlermeldung überschreibt WP Fehlermeldung
			return new WP_Error ( 'denied', __("") );	//Dieser Fehler würde von WP überschrieben, muss aber zum Abbruch erfolgen
		}
		
		// Funke Kreuzmich an
		
		// Beginne mit der cURL Session
		$ch = curl_init($this->km_url . "/extAuth/json");
		// Session Optionen
		curl_setopt($ch, CURLOPT_USERPWD, $this->km_options["http_user"] . ':' . $this->km_options["http_password"]); // HTTP Benutzer und Passwort aus den Einstellungen
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('username' => $username, 'password' => $password))); //Benutzername und Passwort aus Loginfeld
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		// cURL ausfuehren
		$jsondata = @curl_exec($ch);
		// Liest die huebsche JSON in einen Array
	    $ext_auth = @json_decode($jsondata, true);
		$auth_user = $ext_auth["user"]; // macht einen Array aus den Angaben im user array
		// Session Ende
		curl_close($ch);
		
		
		// Verarbeite Daten von Kreuzmich
		
		/* Kreuzmich Server down:
		*
		* kein JSON oder JSON != erwarteter Array (e.g. Kreuzmich error 500: JSON = HTML der Error 500 Seite)
		*
		* in diesem Fall wird die WordPress-eigene Auth Funktion nicht deaktiviert & bleibt aktiv.
		* WordPress-eigene Loginmethode überschreibt unseren hier ausgegebene WP_Error mit "Username/Pw falsch"
		* Gib deshalb die Meldung, dass der Server down ist, als extra WordPress message über der WordPress-Fehlermeldung aus
		*
		* Ist der Server nicht down, kein return, sondern Entfernen der WordPress-eigenen Auth Methode und weiter im Code
		*/
		if (!isset($ext_auth['success'])) {
			
			add_filter( 'login_errors', array($this, 'km_server_offline_error')); //eigene Login Fehlermeldung überschreibt WP Fehlermeldung
			return new WP_Error( 'denied', __("") ); //Dieser Fehler würde von WP überschrieben, muss aber zum Abbruch erfolgen
			
		} else remove_action('authenticate', 'wp_authenticate_username_password', 20); // Server online! WP-eigene Methode abgeschaltet
		
		
		// Login fehlerhaft (Name, Passwort, nicht freigeschaltet), sofort Fehlerausgabe
		if (!$ext_auth['success'])
			return new WP_Error( 'denied', $this->km_options['error_message']);
		
		// Login erfolgreich aber abgelaufenes Konto und abgelaufene Konten nicht erlaubt, sofort Fehlerausgabe
		if (($auth_user['expired']) && (!$this->km_options['expired_users']))
			return new WP_Error( 'denied', $this->km_options['error_message_expired']);
		
		// Login erfolgreich
		
		// bereite Kreuzmich Daten für WordPress vor
		$userdata = array(  'user_email' => $auth_user['email'],    	//muss unique sein, so wie bei kreuzmich
									'user_login' => $auth_user['username'],   				//den usernamen uebernehmen wir aus kreuzmich
									// 'user_pass' => $password, 				//das Passwort aus Loginfeld wird in WordPress Db uebernommen (nicht empfohlen) 
									'first_name' => $auth_user['firstname'],	//den vornamen und
									'last_name' => $auth_user['lastname']   	//den nachnamen uebernehmen wir auch aus kreuzmich
									);
		
		//suche existierenden Benutzer bei WordPress
		$userobj = new WP_User(); // erstelle leeres User Object
		$user = $userobj->get_data_by( 'login', $username ); // suche WordPress Datenbank nach kreuzmich username, fuelle leeres User Object mit Benutzerdaten
		
		// keine Benutzer ID gefunden -> lege neuen Benutzer an
		if( empty($user->ID) ) {
	 
			// Plugin-Einstellungen: Duerfen neue Benutzer hinzugefuegt werden?
			if ($this->km_options['new_users']) {

			// A: Ja, erstelle neuen Benutzer
										
				// Schreibe neuen Benutzer in WordPress Datenbank, verschluesselt auch ein evtl. Passwort
				$new_user_id = wp_insert_user($userdata); 
	 
				// Lade erstellten Benutzer fuer Login
				$user = new WP_User ($new_user_id);
			}
			// B: Nein, keine neuen Benutzer erlaubt -> Fehlerausgabe
			else return new WP_Error( 'denied', __("<strong>Fehler</strong>: Dieses Konto existiert zwar auf <span id='km'></span>, aber nicht im System der Homepage. Da wir aktuell keine neuen Benutzer zulassen, kannst du dich nicht einloggen.") );
		
		} else {
		
			// update gefundenen Benutzer mit den Daten aus Kreuzmich, nimm userdata und zusätzlich die ID
			$user_id = wp_update_user( array_merge ( ['ID' => $user->ID], $userdata ) );

			$user = new WP_User($user->ID); // Lade gefundenen Benutzer fuer Login, man kann hier auch $user_id verwenden	

		}	
				
		// hole Berechtigungen aus externer Quelle, falls in Einstellungen erlaubt
		if ($this->km_options['forum_permissions'])
			$user = $this->get_additional_user_permissions($user);

		
		// gib geladenen $user zurueck
		return $user;
	}

	public function km_privacy_message() {
		return '<p class="message">Mit der Eingabe von Kreuzmich Nutzernamen und Passwort willigst du in die Datenübertragung deiner Benutzerdaten von Kreuzmich zur Fachschaft ein.</p>';
	}
	
	public function km_server_offline_error() {
		return __("<strong>Der <span id='km'></span> Server scheint offline zu sein!</strong>");
	}
	
	public function km_umlaut_error() {
		return __("<strong>Fehler:</strong><br>Der Benutzername enthält Umlaute.<br>Aus technischen Gründen müssen Umlaute auf der Homepage ersetzt werden:<br> Bitte schreibe z.B. <strong>ein ä als a - nicht als ae!</strong> Ersetze dementsprechend ö & ü durch o & u. Ein ß wird zu s, nicht ss.");
	}
	
	// WP Passwort vergessen umleiten auf Kreuzmichs Passwort vergessen 
	public function lostpassword_page_redirect_to_kreuzmich() {
	global $pagenow;
	//bist du auf /wp-login.php?action=lostpassword
		if ( ( strtolower($pagenow) == 'wp-login.php') && (isset($_GET['action'])) && ( strtolower( $_GET['action']) == 'lostpassword' ) ) 
			// leite um zu Kreuzmichs Passwort zuruecksetzen
			wp_redirect( $this->km_url . '/sfGuardAuth/newPassword' );
	}
	
	
	// WP Registrierung umleiten auf Kreuzmichs Registrierung
	public function registration_page_redirect_to_kreuzmich() {
	global $pagenow;
	//bist du auf /wp-login.php?action=register
		if ( ( strtolower($pagenow) == 'wp-login.php') && (isset($_GET['action'])) && ( strtolower( $_GET['action']) == 'register' ) ) 
			// leite um zu Kreuzmich Registrierung
			wp_redirect( $this->km_url . '/signUp/index' );
	}
	
	/* 
	* Problem: WordPress Funktion sanitze_user ersetzt automatisch alle Umlaute in Loginnamen, zeitlich vor unserer Funktion km_auth
	* ä = ae, ö = oe, ü = ue, ß = ss
	* km_auth erhaelt schon bereinigten Username mit "ae" usw. und schickt ihn zu Kreuzmich
	* Die Kollation der Kreuzmich Datenbank ist utf8_general_ci, für Kreuzmich ist ä=a, ö=o, ü=u, ß=s
	* Resultat: Kreuzmich erkennt Benutzernamen nicht & verweigert Login
	* Loesung: fange per Filter den unbereinigten Usernamen $raw_username vorher ab und speichere in globaler Variable $username_roh
	*
	* Filter sanitize_user in wp-includes/formatting.php:
	*
	* param $raw_username	string		der Username vor der Bereinigung durch WordPress
	* param $strict			boolean		limitiert Zeichen auf alphanumerisch _, Leerzeichen, ., -, *, @ 
	* return $username		string		der Username nach Bereinigung
	*
	* Auth Funktion km_auth prueft spaeter $username_roh auf umlaute & gibt Fehler aus, damit man sich ohne Umlaut einloggt.
	* Beispiel: "Gummibär" => wuerde durch sanitize_user zu "Gummibaer" => Bei Kreuzmich gibt es nur Gummibär, deshalb gib Fehlermeldung bei WordPress aus
	* Lösung: Tippe Gummibar ein, Gummibar wird zu Kreuzmich geschickt, da für Kreuzmich ä=a, findet Kreuzmich bei sich Gummibär und erlaubt Login
	*/
	public function wordpress_save_umlaut_username ($username, $raw_username, $strict) {
	
		// speichere unbearbeiteten Benutzernamen global
		$this->username_roh = $raw_username; 
		
		//wir koennten mit diesem Filter $username auch veraendern
		//z.B. anpassen für UTF8_gerneral_ci von Kreuzmich, also ä=a, ö=o, ü=u, ß=s
		//keine moeglichkeit den usern nach Login anzuzeigen, dass ihr Name automatisch geaendert wurde
		//deshalb als fehlermeldung vor login
		//Fehlermeldung kann nicht hier ausgegeben werden, muss spaeter in km_auth geschehen
		
		return $username; // Rueckgabe $username unveraendert
	}
	
	/* Passe Berechtigungen anhand einer weiteren Funktion, z.B. einer weiteren externen Quelle an */
		
	public function get_additional_user_permissions ($user) {
					
		return $user; //Rückgabe des neue WP user Objektes
	}

}

$my_kreuzmich_authentification = new Kreuzmich_Authentification();

?>