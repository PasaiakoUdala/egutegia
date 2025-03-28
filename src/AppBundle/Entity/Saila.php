<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Saila
 *
 * @ORM\Table(name="saila")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\SailaRepository")
 */
class Saila
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
     * @var \AppBundle\Entity\User
     *
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\User", mappedBy="saila")
     */
    private $users;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="AppBundle\Entity\User", mappedBy="zinegotziSailak")
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
        $this->users = new \Doctrine\Common\Collections\ArrayCollection();
        $this->zinegotziak = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @return Saila
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
     * @param \AppBundle\Entity\User $user
     *
     * @return Saila
     */
    public function addUser(\AppBundle\Entity\User $user)
    {
        $this->users[] = $user;

        return $this;
    }

    /**
     * Remove user.
     *
     * @param \AppBundle\Entity\User $user
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeUser(\AppBundle\Entity\User $user)
    {
        return $this->users->removeElement($user);
    }

    /**
     * Get users.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getZinegotziak()
    {
        return $this->zinegotziak;
    }

    /**
     * @param User $zinegotzia
     *
     * @return self
     */
    public function addZinegotzia(User $zinegotzia)
    {
        if (!$this->zinegotziak->contains($zinegotzia)) {
            $this->zinegotziak[] = $zinegotzia;
            $zinegotzia->addZinegotziSaila($this);
        }

        return $this;
    }

    /**
     * @param User $zinegotzia
     *
     * @return self
     */
    public function removeZinegotzia(User $zinegotzia)
    {
        if ($this->zinegotziak->contains($zinegotzia)) {
            $this->zinegotziak->removeElement($zinegotzia);
            $zinegotzia->removeZinegotziSaila($this);
        }

        return $this;
    }

    /**
     * Add zinegotziak.
     *
     * @param \AppBundle\Entity\User $zinegotziak
     *
     * @return Saila
     */
    public function addZinegotziak(\AppBundle\Entity\User $zinegotziak)
    {
        $this->zinegotziak[] = $zinegotziak;

        return $this;
    }

    /**
     * Remove zinegotziak.
     *
     * @param \AppBundle\Entity\User $zinegotziak
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeZinegotziak(\AppBundle\Entity\User $zinegotziak)
    {
        return $this->zinegotziak->removeElement($zinegotziak);
    }
}
