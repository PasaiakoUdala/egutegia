<?php

/*
 *     Iker Ibarguren <@ikerib>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AppBundle\Ldap;

use AppBundle\Entity\User;
use FR3D\LdapBundle\Hydrator\HydratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserHydrator implements HydratorInterface
{
    /**
     * Populate an user with the data retrieved from LDAP.
     *
     * @param array $ldapEntry LDAP result information as a multi-dimensional array.
     *                         see {@link http://www.php.net/function.ldap-get-entries.php} for array format examples.
     *
     * @return UserInterface
     */
    public function hydrate(array $ldapEntry)
    {
        $user = new User();
        $user->setUsername($ldapEntry[ 'samaccountname' ][ 0 ]);
        $user->setEmail($ldapEntry[ 'mail' ][ 0 ]);
        if (array_key_exists('department', $ldapEntry) && \count($ldapEntry[ 'department' ])) {
            $user->setDepartment($ldapEntry[ 'department' ][ 0 ]);
        }
        if (array_key_exists('employeeid', $ldapEntry) && \count($ldapEntry[ 'employeeid' ])) {
            $user->setNan($ldapEntry[ 'employeeid' ][ 0 ]);
        }
        if (array_key_exists('description', $ldapEntry) && \count($ldapEntry[ 'description' ])) {
            $user->setLanpostua($ldapEntry[ 'description' ][ 0 ]);
        }
        if (array_key_exists('displayname', $ldapEntry) && \count($ldapEntry[ 'displayname' ])) {
            $user->setDisplayname($ldapEntry[ 'displayname' ][ 0 ]);
        }
        if (array_key_exists('memberof', $ldapEntry) && \count($ldapEntry[ 'memberof' ])) {
            $members = $ldapEntry[ 'memberof' ];
            $udaltzainAdministrariaDa = false;
            foreach ($members as $key => $value) {
                $sp = ldap_explode_dn($value, 1);
                if ($sp[ 0 ] === 'APP-Web_Egutegia') {
                    $rol = 'ROLE_ADMIN';
                    $user->addRole($rol);
                }
                if ($sp[ 0 ] === 'APP-Web_Egutegia-Bideratzaile') {
                    $rol = 'ROLE_BIDERATZAILEA';
                    $user->addRole($rol);
                    $rol = 'ROLE_ADMIN';
                    $user->addRole($rol);
                }
                if ($sp[ 0 ] === 'ROL-Antolakuntza_Informatika') {
                    $rol = 'ROLE_SUPER_ADMIN';
                    $user->addRole($rol);
                }
                if (strpos($sp[ 0 ], 'Saila-Udaltzaingoa') !== false) { // UDALTZAINA BADA
                    $rol = 'ROLE_UDALTZAINA';
                    $user->setMunipada(true);
                    $user->addRole($rol);
                }

                if (strpos($sp[ 0 ], 'App-Web_Egutegia-Sinatzailea') !== false) {
                    $rol = 'ROLE_SINATZAILEA';
                    $user->addRole($rol);
                }
                if (strpos($sp[ 0 ], 'Sailburu') !== false) { //
                    $rol = 'ROLE_SAILBURUA';
                    $user->addRole($rol);
                }
                if (strpos($sp[ 0 ], 'Zinegotziak') !== false) { //
                    $rol = 'ROLE_ZINEGOTZIA';
                    $user->addRole($rol);
                }
                if (strpos($sp[ 0 ], 'ROL-Udaltzaingoa_Administrazioa') !== false) { // UDALTZAINA BADA
                    $udaltzainAdministrariaDa = true;
                }
            }

            if ($udaltzainAdministrariaDa) { // Udaltzaingoan dagoen administraria denez, egutegira sartu behar du, beraz ROLE_UDALTZAINA kendu
                $user->removeRole('ROLE_UDALTZAINA');
            }

            if ($user->getSailburuada()) {
                $user->addRole('ROLE_SAILBURUA');
            }
            $user->setMembers($ldapEntry[ 'memberof' ]);
        }
        if (array_key_exists('preferredlanguage', $ldapEntry) && \count($ldapEntry[ 'preferredlanguage' ])) {
            $user->setHizkuntza($ldapEntry[ 'preferredlanguage' ][ 0 ]);
        }
        $user->setDn($ldapEntry[ 'dn' ]);
        $user->setEnabled(true);
        $user->setPassword('');

        return $user;
    }
}
