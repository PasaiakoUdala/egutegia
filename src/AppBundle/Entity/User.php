<?php

/*
 *     Iker Ibarguren <@ikerib>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use FOS\UserBundle\Model\User as BaseUser;
use FR3D\LdapBundle\Model\LdapUserInterface;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;

/**
 * User.
 *
 * @ORM\Table(name="user")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\UserRepository")
 * @ExclusionPolicy("all")
 */
class User extends BaseUser implements LdapUserInterface
{
    /**
     * @var int
     * @Expose
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     */
    protected $dn;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Expose
     */
    protected $department;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Expose
     */
    protected $displayname;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Expose
     */
    protected $nan;

    /**
     * @ORM\Column(type="boolean", length=255, nullable=true, options={"default": false})
     * @Expose
     */
    protected $sailburuada;

    /**
     * @ORM\Column(type="boolean", length=255, nullable=true, options={"default": false})
     * @Expose
     */
    protected $munipada;

    /**
     * @ORM\Column(type="boolean", length=255, nullable=true, options={"default": false})
     * @Expose
     */
    protected $aktibo;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Expose
     */
    protected $ldapsaila;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Expose
     */
    protected $hizkuntza;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Expose
     */
    protected $lanpostua;

    /**
     * @var bool
     *
     * @ORM\Column(name="sailburua", type="boolean", nullable=true)
     */
    private $sailburua;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Expose
     */
    protected $notes;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $members = [];

    ///**
    // * @var members[]
    // * @ORM\Column(type="string")
    // */
    //protected $members;

    /*****************************************************************************************************************/
    /*** ERLAZIOAK ***************************************************************************************************/
    /*****************************************************************************************************************/

    /**
     * @var Collection
     *
     * @ORM\ManyToMany(targetEntity="AppBundle\Entity\Taldea", inversedBy="zinegotziak")
     * @ORM\JoinTable(name="zinegotzi_taldea",
     *      joinColumns={@ORM\JoinColumn(name="user_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="taldea_id", referencedColumnName="id")}
     * )
     */
    private $zinegotziTaldeak;

    /**
     * @var calendars[]
     *
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Calendar", mappedBy="user",cascade={"persist"})
     * @ORM\OrderBy({"year" = "DESC", "name"="ASC"})
     */
    private $calendars;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Message", mappedBy="user",cascade={"persist"})
     * @ORM\OrderBy({"name" = "ASC"})
     */
    private $messages;

    /**
     * @var \AppBundle\Entity\Notification
     *
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Notification", mappedBy="user")
     */
    protected $notifications;

    /**
     * @var \AppBundle\Entity\Eskaera
     *
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Eskaera", mappedBy="user")
     */
    protected $eskaera;

    /**
     * @var \AppBundle\Entity\Firmadet
     *
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Firmadet", mappedBy="firmatzailea")
     */
    protected $firmadet;

    /**
     * @var \AppBundle\Entity\Saila
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Saila", inversedBy="users")
     * @ORM\JoinColumn(name="saila_id", referencedColumnName="id", onDelete="SET NULL", nullable=true)
     */
    private $saila;

    /**
     * @var \AppBundle\Entity\Taldea
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Taldea", inversedBy="users")
     * @ORM\JoinColumn(name="taldea_id", referencedColumnName="id", onDelete="SET NULL", nullable=true)
     */
    private $taldea;


    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->zinegotziSailak = new ArrayCollection();
        $this->members = [];
        $this->calendars = new ArrayCollection();
        if (empty($this->roles)) {
            $this->roles[] = 'ROLE_USER';
        }
    }

    public function __toString()
    {
        return $this->getUsername();
    }

    /*****************************************************************************************************************/
    /*****************************************************************************************************************/
    /*****************************************************************************************************************/

    //public function addMembers($member)  {
    //    if (!in_array($member, $this->members, true)) {
    //        $this->members[] = $member;
    //    }
    //
    //    return $this;
    //}
    //
    //public function getMembers()
    //{
    //    return array_unique($this->members);
    //}
    //
    //public function hasMembers($member)
    //{
    //    return in_array(strtoupper($member), $this->getMembers(), true);
    //}

    public function getMembers()
    {
        return $this->members;
    }

    public function setMembers(array $members)
    {
        $this->members = $members;

        // allows for chaining
        return $this;
    }

    /**
     * Set Ldap Distinguished Name.
     *
     * @param string $dn Distinguished Name
     */
    public function setDn($dn)
    {
        $this->dn = $dn;
    }

    /**
     * Get Ldap Distinguished Name.
     *
     * @return null|string Distinguished Name
     */
    public function getDn()
    {
        return $this->dn;
    }

    /**
     * Add calendar.
     *
     * @param \AppBundle\Entity\Calendar $calendar
     *
     * @return User
     */
    public function addCalendar(\AppBundle\Entity\Calendar $calendar)
    {
        $this->calendars[] = $calendar;

        return $this;
    }

    /**
     * Remove calendar.
     *
     * @param \AppBundle\Entity\Calendar $calendar
     */
    public function removeCalendar(\AppBundle\Entity\Calendar $calendar)
    {
        $this->calendars->removeElement($calendar);
    }

    /**
     * Get calendars.
     *
     * @return Collection
     */
    public function getCalendars()
    {
        return $this->calendars;
    }

    /**
     * Set department.
     *
     * @param string $department
     *
     * @return User
     */
    public function setDepartment($department)
    {
        $this->department = $department;

        return $this;
    }

    /**
     * Get department.
     *
     * @return string
     */
    public function getDepartment()
    {
        return $this->department;
    }

    /**
     * Set displayname.
     *
     * @param string $displayname
     *
     * @return User
     */
    public function setDisplayname($displayname)
    {
        $this->displayname = $displayname;

        return $this;
    }

    /**
     * Get displayname.
     *
     * @return string
     */
    public function getDisplayname()
    {
        return $this->displayname;
    }

    /**
     * Set nan.
     *
     * @param string $nan
     *
     * @return User
     */
    public function setNan($nan)
    {
        $this->nan = $nan;

        return $this;
    }

    /**
     * Get nan.
     *
     * @return string
     */
    public function getNan()
    {
        return $this->nan;
    }

    /**
     * Set lanpostua.
     *
     * @param string $lanpostua
     *
     * @return User
     */
    public function setLanpostua($lanpostua)
    {
        $this->lanpostua = $lanpostua;

        return $this;
    }

    /**
     * Get lanpostua.
     *
     * @return string
     */
    public function getLanpostua()
    {
        return $this->lanpostua;
    }

    /**
     * Set notes.
     *
     * @param string $notes
     *
     * @return User
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * Get notes.
     *
     * @return string
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Add notification
     *
     * @param \AppBundle\Entity\Notification $notification
     *
     * @return User
     */
    public function addNotification(\AppBundle\Entity\Notification $notification)
    {
        $this->notifications[] = $notification;

        return $this;
    }

    /**
     * Remove notification
     *
     * @param \AppBundle\Entity\Notification $notification
     */
    public function removeNotification(\AppBundle\Entity\Notification $notification)
    {
        $this->notifications->removeElement($notification);
    }

    /**
     * Get notifications
     *
     * @return Collection
     */
    public function getNotifications()
    {
        return $this->notifications;
    }

    /**
     * Add eskaera
     *
     * @param \AppBundle\Entity\Eskaera $eskaera
     *
     * @return User
     */
    public function addEskaera(\AppBundle\Entity\Eskaera $eskaera)
    {
        $this->eskaera[] = $eskaera;

        return $this;
    }

    /**
     * Remove eskaera
     *
     * @param \AppBundle\Entity\Eskaera $eskaera
     */
    public function removeEskaera(\AppBundle\Entity\Eskaera $eskaera)
    {
        $this->eskaera->removeElement($eskaera);
    }

    /**
     * Get eskaera
     *
     * @return Collection
     */
    public function getEskaera()
    {
        return $this->eskaera;
    }

    /**
     * Add firmadet
     *
     * @param \AppBundle\Entity\Firmadet $firmadet
     *
     * @return User
     */
    public function addFirmadet(\AppBundle\Entity\Firmadet $firmadet)
    {
        $this->firmadet[] = $firmadet;

        return $this;
    }

    /**
     * Remove firmadet
     *
     * @param \AppBundle\Entity\Firmadet $firmadet
     */
    public function removeFirmadet(\AppBundle\Entity\Firmadet $firmadet)
    {
        $this->firmadet->removeElement($firmadet);
    }

    /**
     * Get firmadet
     *
     * @return Collection
     */
    public function getFirmadet()
    {
        return $this->firmadet;
    }


    /**
     * Set hizkuntza.
     *
     * @param string|null $hizkuntza
     *
     * @return User
     */
    public function setHizkuntza($hizkuntza = null)
    {
        $this->hizkuntza = $hizkuntza;

        return $this;
    }

    /**
     * Get hizkuntza.
     *
     * @return string|null
     */
    public function getHizkuntza()
    {
        return $this->hizkuntza;
    }

    /**
     * Set sailburuada.
     *
     * @param bool|null $sailburuada
     *
     * @return User
     */
    public function setSailburuada($sailburuada = null)
    {
        $this->sailburuada = $sailburuada;

        return $this;
    }

    /**
     * Get sailburuada.
     *
     * @return bool|null
     */
    public function getSailburuada()
    {
        return $this->sailburuada;
    }

    /**
     * Set ldapsaila.
     *
     * @param string|null $ldapsaila
     *
     * @return User
     */
    public function setLdapsaila($ldapsaila = null)
    {
        $this->ldapsaila = $ldapsaila;

        return $this;
    }

    /**
     * Get ldapsaila.
     *
     * @return string|null
     */
    public function getLdapsaila()
    {
        return $this->ldapsaila;
    }

    /**
     * Add message.
     *
     * @param \AppBundle\Entity\Message $message
     *
     * @return User
     */
    public function addMessage(\AppBundle\Entity\Message $message)
    {
        $this->messages[] = $message;

        return $this;
    }

    /**
     * Remove message.
     *
     * @param \AppBundle\Entity\Message $message
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeMessage(\AppBundle\Entity\Message $message)
    {
        return $this->messages->removeElement($message);
    }

    /**
     * Get messages.
     *
     * @return Collection
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * Set munipada.
     *
     * @param bool|null $munipada
     *
     * @return User
     */
    public function setMunipada($munipada = null)
    {
        $this->munipada = $munipada;

        return $this;
    }

    /**
     * Get munipada.
     *
     * @return bool|null
     */
    public function getMunipada()
    {
        return $this->munipada;
    }

    /**
     * Set aktibo.
     *
     * @param bool|null $aktibo
     *
     * @return User
     */
    public function setAktibo($aktibo = null)
    {
        $this->aktibo = $aktibo;

        return $this;
    }

    /**
     * Get aktibo.
     *
     * @return bool|null
     */
    public function getAktibo()
    {
        return $this->aktibo;
    }

    /**
     * Set saila.
     *
     * @param \AppBundle\Entity\Saila|null $saila
     *
     * @return User
     */
    public function setSaila(\AppBundle\Entity\Saila $saila = null)
    {
        $this->saila = $saila;

        return $this;
    }

    /**
     * Get saila.
     *
     * @return \AppBundle\Entity\Saila|null
     */
    public function getSaila()
    {
        return $this->saila;
    }

    /**
     * Set sailburua.
     *
     * @param bool|null $sailburua
     *
     * @return User
     */
    public function setSailburua($sailburua = null)
    {
        $this->sailburua = $sailburua;

        return $this;
    }

    /**
     * Get sailburua.
     *
     * @return bool|null
     */
    public function getSailburua()
    {
        return $this->sailburua;
    }

    /**
     * @return Collection
     */
    public function getZinegotziSailak()
    {
        return $this->zinegotziSailak;
    }

    /**
     * @param Saila $saila
     *
     * @return self
     */
    public function addZinegotziSaila(Saila $saila)
    {
        if (!$this->zinegotziSailak->contains($saila)) {
            $this->zinegotziSailak[] = $saila;
        }

        return $this;
    }

    /**
     * @param Saila $saila
     *
     * @return self
     */
    public function removeZinegotziSaila(Saila $saila)
    {
        if ($this->zinegotziSailak->contains($saila)) {
            $this->zinegotziSailak->removeElement($saila);
        }

        return $this;
    }

    /**
     * Add zinegotziSailak.
     *
     * @param \AppBundle\Entity\Saila $zinegotziSailak
     *
     * @return User
     */
    public function addZinegotziSailak(\AppBundle\Entity\Saila $zinegotziSailak)
    {
        $this->zinegotziSailak[] = $zinegotziSailak;

        return $this;
    }

    /**
     * Remove zinegotziSailak.
     *
     * @param \AppBundle\Entity\Saila $zinegotziSailak
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeZinegotziSailak(\AppBundle\Entity\Saila $zinegotziSailak)
    {
        return $this->zinegotziSailak->removeElement($zinegotziSailak);
    }

    /**
     * Set taldea.
     *
     * @param \AppBundle\Entity\Taldea|null $taldea
     *
     * @return User
     */
    public function setTaldea(\AppBundle\Entity\Taldea $taldea = null)
    {
        $this->taldea = $taldea;

        return $this;
    }

    /**
     * Get taldea.
     *
     * @return \AppBundle\Entity\Taldea|null
     */
    public function getTaldea()
    {
        return $this->taldea;
    }

    /**
     * Add zinegotziTaldeak.
     *
     * @param \AppBundle\Entity\Saila $zinegotziTaldeak
     *
     * @return User
     */
    public function addZinegotziTaldeak(\AppBundle\Entity\Taldea $zinegotziTaldeak)
    {
        $this->zinegotziTaldeak[] = $zinegotziTaldeak;

        return $this;
    }

    /**
     * Remove zinegotziTaldeak.
     *
     * @param \AppBundle\Entity\Taldea $zinegotziTaldeak
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeZinegotziTaldeak(\AppBundle\Entity\Taldea $zinegotziTaldeak)
    {
        return $this->zinegotziTaldeak->removeElement($zinegotziTaldeak);
    }

    /**
     * Get zinegotziTaldeak.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getZinegotziTaldeak()
    {
        return $this->zinegotziTaldeak;
    }
}
