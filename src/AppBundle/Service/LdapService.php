<?php
/**
 * Created by PhpStorm.
 * User: iibarguren
 * Date: 5/31/17
 * Time: 8:41 AM
 */

namespace AppBundle\Service;



class LdapService
{
    private $ip;
    private $ldap_username;
    private $basedn;
    private $passwd;


    public function __construct($ip, $ldap_username, $basedn, $passwd)
    {
        $this->ip = $ip;
        $this->ldap_username = $ldap_username;
        $this->basedn = $basedn;
        $this->passwd = $passwd;
    }


    public function getAllUsers(): array
    {
        $ip       = $this->ip;
        $ldap_username = $this->ldap_username;
        $basedn   = $this->basedn;
        $passwd   = $this->passwd;


        $ldap = ldap_connect($ip) or die('Could not connect to LDAP');

        ldap_bind($ldap, $ldap_username, $passwd) or die('Could not bind to LDAP');


//        $gFilter = '(&(objectClass=posixAccount)(CN=^*,CN=Users,DC=pasaia,DC=net))';
        $gFilter = '(&(objectCategory=person)(objectClass=user))';
        $gAttr = array('samAccountName');
        $result = ldap_search($ldap, $basedn, $gFilter, $gAttr) or exit('Unable to search LDAP server');
        $ldapusers = ldap_get_entries($ldap, $result);
        $users = [];
        foreach ($ldapusers as $key => $value) {
            if ($key !== 'count') {
                $username = $value[ 'samaccountname' ][ 0 ];
                $users[]  = $username;
            }
        }

        return $users;
    }

    public function getGroupUsersRecurive($groupname): array
    {
        $ip       = $this->ip;
        $ldap_username = $this->ldap_username;
        $basedn   = $this->basedn;
        $passwd   = $this->passwd;


        $ldap = ldap_connect($ip) or die('Could not connect to LDAP');

        ldap_bind($ldap, $ldap_username, $passwd) or die('Could not bind to LDAP');


        $gFilter = "(&(objectClass=posixAccount)(memberOf:1.2.840.113556.1.4.1941:=CN=$groupname,CN=Users,DC=pasaia,DC=net))";
        $gAttr = array('samAccountName');
        $result = ldap_search($ldap, $basedn, $gFilter, $gAttr) or exit('Unable to search LDAP server');
        $ldapusers = ldap_get_entries($ldap, $result);
        $users = [];
        foreach ($ldapusers as $key => $value) {
            if ($key !== 'count') {
                $username = $value[ 'samaccountname' ][ 0 ];
                $users[]  = $username;
            }
        }

        return $users;
    }

    public function checkSailburuada($username): array
    {
        $ip       = $this->ip;
        $ldap_username = $this->ldap_username;
        $basedn   = $this->basedn;
        $passwd   = $this->passwd;
        $resp = [];

        $ldap = ldap_connect($ip) or die('Could not connect to LDAP');
        ldap_bind($ldap, $ldap_username, $passwd) or die('Could not bind to LDAP');

        // Sailburuada
        $gFilter = "(&(samAccountName=$username)(memberOf:1.2.840.113556.1.4.1941:=CN=Taldea-Sailburuak,CN=Users,DC=pasaia,DC=net))";
        $gAttr = array('samAccountName');
        $result = ldap_search($ldap, $basedn, $gFilter, $gAttr) or exit('Unable to search LDAP server');
        $result = ldap_get_entries($ldap, $result);
        $resp[ 'sailburuada' ] = $result[ 'count' ];

        // Saila
        $gFilter = "(member:1.2.840.113556.1.4.1941:=cn=$username,cn=users,dc=pasaia,dc=net)";
        $result = ldap_search($ldap, $basedn, $gFilter, $gAttr) or exit('Unable to search LDAP server');
        $result2 = ldap_get_entries($ldap, $result);

        foreach ($result2 as $key => $value) {
            if ($key !== 'count') {
                $taldea = $value[ 'samaccountname' ][0];
                if (strpos($taldea, 'Saila') === 0) {
                    $resp[ 'saila' ] = explode('-', $taldea)[ 1 ];
                }
            }
        }

        return $resp;
    }

    public function checkArduraduna($username): array
    {
        $ip       = $this->ip;
        $ldap_username = $this->ldap_username;
        $basedn   = $this->basedn;
        $passwd   = $this->passwd;
        $resp = [];

        $ldap = ldap_connect($ip) or die('Could not connect to LDAP');
        ldap_bind($ldap, $ldap_username, $passwd) or die('Could not bind to LDAP');

        // Sailburuada
        $gFilter = "(&(samAccountName=$username)(memberOf:1.2.840.113556.1.4.1941:=CN=App-Web_Egutegia-Arduraduna,CN=Users,DC=pasaia,DC=net))";
        $gAttr = array('samAccountName');
        $result = ldap_search($ldap, $basedn, $gFilter, $gAttr) or exit('Unable to search LDAP server');
        $result = ldap_get_entries($ldap, $result);
        $resp[ 'alkateada' ] = $result[ 'count' ];

        return $resp;
    }

    public function dagoErabiltzaileaLdapTaldean($username, $ldapTaldea): array
    {
        $ip       = $this->ip;
        $ldap_username = $this->ldap_username;
        $basedn   = $this->basedn;
        $passwd   = $this->passwd;
        $resp = [];

        $ldap = ldap_connect($ip) or die('Could not connect to LDAP');
        ldap_bind($ldap, $ldap_username, $passwd) or die('Could not bind to LDAP');

        // Sailburuada
        $gFilter = "(&(samAccountName=$username)(memberOf:1.2.840.113556.1.4.1941:=CN=$ldapTaldea,CN=Users,DC=pasaia,DC=net))";
        $gAttr = array('samAccountName');
        $result = ldap_search($ldap, $basedn, $gFilter, $gAttr) or exit('Unable to search LDAP server');
        $result = ldap_get_entries($ldap, $result);
        $resp[ 'dago' ] = $result[ 'count' ];

        return $resp;
    }
}
