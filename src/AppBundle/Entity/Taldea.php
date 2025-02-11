<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Taldea
 *
 * @ORM\Table(name="taldea")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\TaldeaRepository")
 */
class Taldea
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="izena", type="string", length=255)
     */
    private $izena;

    /*****************************************************************************************************************/
    /*** FUNTZIOAK ***************************************************************************************************/
    /*****************************************************************************************************************/

    public function __toString()
    {
        return (string)$this->izena;
    }

    /*****************************************************************************************************************/
    /*** ERLAZIOAK ***************************************************************************************************/
    /*****************************************************************************************************************/

    /**
     * @var User
     *
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\User", mappedBy="taldea")
     */
    private $users;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="AppBundle\Entity\User", mappedBy="zinegotziTaldeak")
     */
    private $zinegotziak;

    /*****************************************************************************************************************/
    /*****************************************************************************************************************/
    /*****************************************************************************************************************/

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->zinegotziak = new ArrayCollection();
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set izena.
     *
     * @param string $izena
     *
     * @return Taldea
     */
    public function setIzena($izena)
    {
        $this->izena = $izena;

        return $this;
    }

    /**
     * Get izena.
     *
     * @return string
     */
    public function getIzena()
    {
        return $this->izena;
    }

    /**
     * Add user.
     *
     * @param User $user
     *
     * @return Taldea
     */
    public function addUser(User $user)
    {
        $this->users[] = $user;

        return $this;
    }

    /**
     * Remove user.
     *
     * @param User $user
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeUser(User $user)
    {
        return $this->users->removeElement($user);
    }

    /**
     * Get users.
     *
     * @return Collection
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * Add zinegotziak.
     *
     * @param User $zinegotziak
     *
     * @return Taldea
     */
    public function addZinegotziak(User $zinegotziak)
    {
        $this->zinegotziak[] = $zinegotziak;

        return $this;
    }

    /**
     * Remove zinegotziak.
     *
     * @param User $zinegotziak
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeZinegotziak(User $zinegotziak)
    {
        return $this->zinegotziak->removeElement($zinegotziak);
    }

    /**
     * Get zinegotziak.
     *
     * @return Collection
     */
    public function getZinegotziak()
    {
        return $this->zinegotziak;
    }
}
