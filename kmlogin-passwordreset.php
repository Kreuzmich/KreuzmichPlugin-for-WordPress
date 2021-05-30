<?php
/* 
* Kreuzmich Plugin Addon: Verhindere WordPress-eigenes Passwort Reset unter Mein Profil, ausser Admins
* Acknowledgment: This PHP file is based on the following plugin by WPBeginner
* http://www.wpbeginner.com/wp-tutorials/how-to-remove-the-password-reset-change-option-from-wordpress/
*/

class Password_Reset_Removed
{

  function __construct() 
  {
    add_filter( 'show_password_fields', array( $this, 'disable' ) ); // Zeige Passwort Felder: JA/NEIN
    add_filter( 'allow_password_reset', array( $this, 'disable' ) ); // Erlaube Passwort Reset: JA/NEIN
    add_filter( 'gettext',              array( $this, 'remove' ) ); // Entferne alle Passwort vergessen? Labels
  }
  
  // Funktion, die bei Admins JA zurueckgibt, beim Rest NEIN
  function disable() 
  {
    if ( is_admin() ) { // bin ich im Dashboard?
      $userdata = wp_get_current_user(); // lade die Daten des aktuellen Benutzers
      $user = new WP_User($userdata->ID); // erstelle neues Benutzerobjekt
      if ( !empty( $user->roles ) && is_array( $user->roles ) && $user->roles[0] == 'administrator' ) // ist dieses Benutzerobjekt Admin?
        return true; // dann ja
    }
    return false; // sonst nein
  }

  // Entferne alle Labels, die Passwort vergessen? anzeigen und ersetze den Text durch "nichts"
  function remove($text) 
  {
    return str_replace( array('Lost your password?', 'Lost your password'), '', trim($text, '?') ); 
  }
}

$pass_reset_removed = new Password_Reset_Removed();

?>