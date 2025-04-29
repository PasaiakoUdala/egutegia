<?php

/*
 *     Iker Ibarguren <@ikerib>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AppBundle\Security\Authentication;

use AppBundle\Entity\Log;
use AppBundle\Entity\User;
use AppBundle\Service\LdapService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use FR3D\LdapBundle\Ldap\LdapManagerInterface;
use FR3D\LdapBundle\Security\Authentication\LdapAuthenticationProvider as BaseProvider;
use FR3D\LdapBundle\Security\User\LdapUserProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\ChainUserProvider;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class LdapAuthenticationProvider extends BaseProvider
{

    /**
     * @var UserProviderInterface
     */
    private $userProvider;

    /**
     * @var LdapManagerInterface
     */
    private $ldapManager;

    /**
     * @var mixed
     */
    private $userManager;

    private $em;

    private $ldapservice;

    /**
     * Constructor.
     *
     * @param UserCheckerInterface   $userChecker                An UserCheckerInterface interface
     * @param string                 $providerKey                A provider key
     * @param UserProviderInterface  $userProvider               An UserProviderInterface interface
     * @param LdapManagerInterface   $ldapManager                An LdapProviderInterface interface
     * @param UserManagerInterface   $userManager
     * @param bool                   $hideUserNotFoundExceptions Whether to hide user not found exception or not
     * @param EntityManagerInterface $em
     * @param                        $ldap_service
     */
    public function __construct(
        UserCheckerInterface $userChecker,
        $providerKey,
        UserProviderInterface $userProvider,
        LdapManagerInterface $ldapManager,
        UserManagerInterface $userManager,
        $hideUserNotFoundExceptions = true,
        EntityManagerInterface $em,
        $ldap_service
    ) {
        parent::__construct($userChecker, $providerKey, $userProvider, $ldapManager, $hideUserNotFoundExceptions);

        $this->userProvider = $userProvider;
        $this->ldapManager = $ldapManager;
        $this->userManager = $userManager;
        $this->em = $em;
        $this->ldapservice = $ldap_service;
    }

    /**
     * {@inheritdoc}
     */
    protected function retrieveUser($username, UsernamePasswordToken $token)
    {
        $user = $token->getUser();
        if ($user instanceof UserInterface) {
            return $user;
        }

        try {
            /** @var User $user */
            $user = $this->userProvider->loadUserByUsername($username);

            if ($this->userProvider instanceof ChainUserProvider) {

                /** @var ChainUserProvider $userProvider */
                $userProvider = $this->userProvider;
                foreach ($userProvider->getProviders() as $provider) {
                    if ($provider instanceof LdapUserProvider) {
                        /** @var User $ldapUser */
                        $ldapUser = $provider->loadUserByUsername($username);
                        $user->setEmail($ldapUser->getEmail());
                        $user->setRoles($ldapUser->getRoles());
                        $user->setMunipada($ldapUser->getMunipada());
                        $user->setDepartment($ldapUser->getDepartment());
                        // Hydrator-eko berdina egiten dugu
                        if ($ldapUser->getNan()) {
                            $user->setNan($ldapUser->getNan());
                        }
                        if ($ldapUser->getLanpostua()) {
                            $user->setLanpostua($ldapUser->getLanpostua());
                        }
                        if ($ldapUser->getDisplayname()) {
                            $user->setDisplayname($ldapUser->getDisplayname());
                        }
                        if ($ldapUser->getMembers()) {
                            $user->setMembers($ldapUser->getMembers());
                        }
                        if ($ldapUser->getDn()) {
                            $user->setDn($ldapUser->getDn());
                        }
                        if ($ldapUser->getHizkuntza()) {
                            $user->setHizkuntza($ldapUser->getHizkuntza());
                        }
                        /** @var LdapService $ldapsrv */
                        $ldapsrv = $this->ldapservice;

                        $sail = $ldapsrv->checkSailburuada($user->getUsername());

                        if ( $user->getSailburua()) {
                            $user->addRole('ROLE_SAILBURUA');
                        }
//                        $user->setSailburuada($sail['sailburuada']);
//                        if ($sail['sailburuada']) {
//                            $user->addRole('ROLE_SAILBURUA');
//                            $user->setLdapsaila($sail[ 'saila' ]);
//                        }

                        $alka =$ldapsrv->checkArduraduna($user->getUsername());
                        if ($alka['alkateada']) {
                            $user->addRole('ROLE_ARDURADUNA');
                            $user->addRole('ROLE_IKUSI_SAILBURUEN_KUADRANTEA');
                        }

                        $ikusiSailArduradunKuadrantea = $ldapsrv->dagoErabiltzaileaLdapTaldean($username, 'APP-Web_Egutegia-Sailburuen-Kuadrantea');
                        if ( $ikusiSailArduradunKuadrantea['dago']) {
                            $user->addRole('ROLE_IKUSI_SAILBURUEN_KUADRANTEA');
                        }
                        $this->userManager->updateUser($user);
                    }
                }
            }

            $log = new Log();
            $log->setName('Login');
            $log->setUser($user);
            $log->setDescription($user->getUsername());
            $this->em->persist($log);
            $this->em->flush();


            return $user;
        } catch (UsernameNotFoundException $notFound) {
            throw $notFound;
        } catch (\Exception $repositoryProblem) {
            $e = new AuthenticationServiceException(
                $repositoryProblem->getMessage(),
                (int) $repositoryProblem->getCode(),
                $repositoryProblem
            );
            $e->setToken($token);

            throw $e;
        }
    }
}
