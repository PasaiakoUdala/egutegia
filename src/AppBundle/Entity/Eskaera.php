<?php

namespace AppBundle\Entity;

use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * Eskaera
 *
 * @ORM\Table(name="eskaera")
 * @Vich\Uploadable
 * @ORM\Entity(repositoryClass="AppBundle\Repository\EskaeraRepository")
 */
class Eskaera
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
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="noiz", type="datetime")
     */
    private $noiz;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="hasi", type="datetime")
     */
    private $hasi;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="amaitu", type="datetime", nullable=true)
     */
    private $amaitu;

    /**
     * @var string
     *
     * @ORM\Column(name="egunak", type="decimal", precision=10, scale=2)
     */
    private $egunak;

    /**
     * @var string
     *
     * @ORM\Column(name="orduak", type="decimal", precision=10, scale=2)
     */
    private $orduak;

    /**
     * @var decimal
     *
     * @ORM\Column(name="total", type="decimal", precision=10, scale=2)
     */
    private $total = 0;

    /**
     * @var decimal
     *
     * @ORM\Column(name="kostua", type="decimal", precision=10, scale=2)
     */
    private $kostua = 0;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $created;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     */
    private $updated;

    /**
     * @var string
     *
     * @ORM\Column(nullable=true)
     * @Gedmo\Blameable(on="change", field={"title", "body"})
     */
    private $contentChangedBy;

    /**
     * @var bool
     * @ORM\Column(name="abiatua", type="boolean", nullable=true, options={"default"=false})
     */
    private $abiatua=false;

    /**
     * @var bool
     * @ORM\Column(name="bertanbehera", type="boolean", nullable=true, options={"default"=false})
     */
    private $bertanbehera=false;

    /**
     * @var bool
     * @ORM\Column(name="bideratua", type="boolean", nullable=true, options={"default"=false})
     */
    private $bideratua=false;

    /**
     * @var bool
     * @ORM\Column(name="amaitua", type="boolean", nullable=true, options={"default"=false})
     */
    private $amaitua=false;

    /**
     * @var bool
     * @ORM\Column(name="egutegian", type="boolean", nullable=true, options={"default"=false})
     */
    private $egutegian=false;

    /**
     * @var bool
     * @ORM\Column(name="konfliktoa", type="boolean", nullable=true, options={"default"=false})
     */
    private $konfliktoa=false;

    /**
     * @var bool
     * @ORM\Column(name="emaitza", type="boolean", nullable=true, options={"default"=false})
     */
    private $emaitza=true;

    /**
     * @var string
     * @ORM\Column(name="oharra", type="string", nullable=true)
     */
    private $oharra;

    /**
     * @var string
     * @ORM\Column(name="nondik", type="string", nullable=true)
     */
    private $nondik;

    /**
     * @var bool
     * @ORM\Column(name="justifikatua", type="boolean", nullable=true, options={"default"=false})
     */
    private $justifikatua=false;

    /**
     *
     * @Vich\UploadableField(mapping="justifikanteak", fileNameProperty="justifikanteName", size="justifikanteSize")
     *
     * @var File
     */
    private $justifikanteFile;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     *
     * @var string
     */
    private $justifikanteName;

    /**
     * @ORM\Column(type="integer", nullable=true)
     *
     * @var integer
     */
    private $justifikanteSize;


    /**
     *
     * @Vich\UploadableField(mapping="justifikanteak", fileNameProperty="ikastaroaName", size="ikastaroaSize")
     *
     * @var File
     */
    private $ikastaroaFile;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     *
     * @var string
     */
    private $ikastaroaName;

    /**
     * @ORM\Column(type="integer", nullable=true)
     *
     * @var integer
     */
    private $ikastaroaSize;

    /*** 2 ***/
    /**
     *
     * @Vich\UploadableField(mapping="justifikanteak", fileNameProperty="ikastaroaName2", size="ikastaroaSize2")
     *
     * @var File
     */
    private $ikastaroaFile2;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     *
     * @var string
     */
    private $ikastaroaName2;

    /**
     * @ORM\Column(type="integer", nullable=true)
     *
     * @var integer
     */
    private $ikastaroaSize2;

    /*** 3 ***/
    /**
     *
     * @Vich\UploadableField(mapping="justifikanteak", fileNameProperty="ikastaroaName3", size="ikastaroaSize3")
     *
     * @var File
     */
    private $ikastaroaFile3;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     *
     * @var string
     */
    private $ikastaroaName3;

    /**
     * @ORM\Column(type="integer", nullable=true)
     *
     * @var integer
     */
    private $ikastaroaSize3;


    /**
     *
     * @Vich\UploadableField(mapping="justifikanteak", fileNameProperty="ordainketaName", size="ordainketaSize")
     *
     * @var File
     */
    private $ordainketaFile;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     *
     * @var string
     */
    private $ordainketaName;

    /**
     * @ORM\Column(type="integer", nullable=true)
     *
     * @var integer
     */
    private $ordainketaSize;

    /**
     * @var bool
     * @ORM\Column(name="ordaindubeharda", type="boolean", nullable=true, options={"default"=false})
     */
    private $ordaindubeharda=false;

    /**
     * @var bool
     * @ORM\Column(name="ordainduta", type="boolean", nullable=true, options={"default"=false})
     */
    private $ordainduta=false;

    /**
     * @var bool
     * @ORM\Column(name="sareko", type="boolean", nullable=true, options={"default"=false})
     */
    private $sareko=false;

    /**
     * @var bool
     * @ORM\Column(name="ikastaroaAmaituta", type="boolean", nullable=true, options={"default"=false})
     */
    private $ikastaroaAmaituta=false;

    /**
     * @var string
     *
     * @ORM\Column(name="erakundea", type="string", length=255)
     */
    private $erakundea;

    /**
     * @var string
     *
     * @ORM\Column(name="aurreikusitako_ordua", type="string", length=255, nullable=true)
     */
    private $aurreikusitakoOrdua;

    /**
     * @var string
     *
     * @ORM\Column(name="aurreikusitako_iraupena", type="string", length=255, nullable=true)
     */
    private $aurreikusitakoIraupena;

    /**
     * @var string
     *
     * @ORM\Column(name="non", type="string", length=255, nullable=true)
     */
    private $non;

    /*****************************************************************************************************************/
    /*** ERLAZIOAK ***************************************************************************************************/
    /*****************************************************************************************************************/

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\User", inversedBy="eskaera")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id",onDelete="CASCADE")
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Type", inversedBy="eskaera")
     * @ORM\JoinColumn(name="type_id", referencedColumnName="id",onDelete="CASCADE")
     */
    private $type;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Calendar", inversedBy="eskaeras")
     * @ORM\JoinColumn(name="calendar_id", referencedColumnName="id",onDelete="CASCADE")
     */
    private $calendar;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Document", mappedBy="eskaera",cascade={"persist", "remove"})
     * @ORM\OrderBy({"orden"="ASC"})
     */
    private $documents;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Sinatzaileak", inversedBy="eskaera")
     * @ORM\JoinColumn(name="sinatzaileak_id", referencedColumnName="id",onDelete="CASCADE")
     */
    private $sinatzaileak;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Notification", mappedBy="eskaera")
     */
    protected $notifications;

    /**
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\Firma", mappedBy="eskaera", cascade={"persist"})
     */
    protected $firma;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Lizentziamota", inversedBy="eskaerak")
     * @ORM\JoinColumn(name="lizentziamota_id", referencedColumnName="id",onDelete="CASCADE")
     * @Expose()
     */
    private $lizentziamota;

    /**
    * Constructor.
    */
    public function __construct()
    {
        $this->orduak = 0;
        $this->egunak = 0;
        $this->noiz = new DateTime();
        $this->abiatua = false;
        $this->amaitua = false;
        $this->konfliktoa = false;
    }

    public function __toString()
    {
        return $this->getName();
    }


    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     *
     * @param File|UploadedFile $image
     *
     * @throws Exception
     */
    public function setJustifikanteFile(?File $image = null): void
    {
        $this->justifikanteFile = $image;

        if (null !== $image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updated = new DateTimeImmutable();
        }
    }

    public function getJustifikanteFile(): ?File
    {
        return $this->justifikanteFile;
    }

    public function setJustifikanteName(?string $justifikanteName): void
    {
        $this->justifikanteName = $justifikanteName;
    }

    public function getJustifikanteName(): ?string
    {
        return $this->justifikanteName;
    }

    public function setJustifikanteSize(?int $justifikanteSize): void
    {
        $this->justifikanteSize = $justifikanteSize;
    }

    public function getJustifikanteSize(): ?int
    {
        return $this->justifikanteSize;
    }


    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     *
     * @param File|UploadedFile $image
     *
     * @throws Exception
     */
    public function setIkastaroaFile(?File $image = null): void
    {
        $this->ikastaroaFile = $image;

        if (null !== $image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updated = new DateTimeImmutable();
        }
    }

    public function getIkastaroaFile(): ?File
    {
        return $this->ikastaroaFile;
    }

    public function setIkastaroaName(?string $ikastaroaName): void
    {
        $this->ikastaroaName = $ikastaroaName;
    }

    public function getIkastaroaName(): ?string
    {
        return $this->ikastaroaName;
    }

    public function setIkastaroaSize(?int $ikastaroaSize): void
    {
        $this->ikastaroaSize = $ikastaroaSize;
    }

    public function getIkastaroaSize(): ?int
    {
        return $this->ikastaroaSize;
    }


    /*** 2 ***/
    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     *
     * @param File|UploadedFile $image
     *
     * @throws Exception
     */
    public function setIkastaroaFile2(?File $image = null): void
    {
        $this->ikastaroaFile2 = $image;

        if (null !== $image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updated = new DateTimeImmutable();
        }
    }

    public function getIkastaroaFile2(): ?File
    {
        return $this->ikastaroaFile2;
    }

    public function setIkastaroaName2(?string $ikastaroaName2): void
    {
        $this->ikastaroaName2 = $ikastaroaName2;
    }

    public function getIkastaroaName2(): ?string
    {
        return $this->ikastaroaName2;
    }

    public function setIkastaroaSize2(?int $ikastaroaSize2): void
    {
        $this->ikastaroaSize2 = $ikastaroaSize2;
    }

    public function getIkastaroaSize2(): ?int
    {
        return $this->ikastaroaSize2;
    }

    /*** 3 ***/
    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     *
     * @param File|UploadedFile $image
     *
     * @throws Exception
     */
    public function setIkastaroaFile3(?File $image = null): void
    {
        $this->ikastaroaFile3 = $image;

        if (null !== $image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updated = new DateTimeImmutable();
        }
    }

    public function getIkastaroaFile3(): ?File
    {
        return $this->ikastaroaFile3;
    }

    public function setIkastaroaName3(?string $ikastaroaName3): void
    {
        $this->ikastaroaName3 = $ikastaroaName3;
    }

    public function getIkastaroaName3(): ?string
    {
        return $this->ikastaroaName3;
    }

    public function setIkastaroaSize3(?int $ikastaroaSize3): void
    {
        $this->ikastaroaSize3 = $ikastaroaSize3;
    }

    public function getIkastaroaSize3(): ?int
    {
        return $this->ikastaroaSize3;
    }

    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     *
     * @param File|UploadedFile $image
     *
     * @throws Exception
     */
    public function setOrdainketaFile(?File $image = null): void
    {
        $this->ordainketaFile = $image;

        if (null !== $image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updated = new DateTimeImmutable();
        }
    }

    public function getOrdainketaFile(): ?File
    {
        return $this->ordainketaFile;
    }

    public function setOrdainketaName(?string $ordainketaName): void
    {
        $this->ordainketaName = $ordainketaName;
    }

    public function getOrdainketaName(): ?string
    {
        return $this->ordainketaName;
    }

    public function setOrdainketaSize(?int $ordainketaSize): void
    {
        $this->ordainketaSize = $ordainketaSize;
    }

    public function getOrdainketaSize(): ?int
    {
        return $this->ordainketaSize;
    }
    /*****************************************************************************************************************/
    /*****************************************************************************************************************/
    /*****************************************************************************************************************/



    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Eskaera
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set hasi
     *
     * @param DateTime $hasi
     *
     * @return Eskaera
     */
    public function setHasi($hasi)
    {
        $this->hasi = $hasi;

        return $this;
    }

    /**
     * Get hasi
     *
     * @return DateTime
     */
    public function getHasi()
    {
        return $this->hasi;
    }

    /**
     * Set amaitu
     *
     * @param DateTime $amaitu
     *
     * @return Eskaera
     */
    public function setAmaitu($amaitu)
    {
        $this->amaitu = $amaitu;

        return $this;
    }

    /**
     * Get amaitu
     *
     * @return DateTime
     */
    public function getAmaitu()
    {
        return $this->amaitu;
    }

    /**
     * Set orduak
     *
     * @param string $orduak
     *
     * @return Eskaera
     */
    public function setOrduak($orduak)
    {
        $this->orduak = $orduak;

        return $this;
    }

    /**
     * Get orduak
     *
     * @return string
     */
    public function getOrduak()
    {
        return $this->orduak;
    }

    /**
     * Set created
     *
     * @param DateTime $created
     *
     * @return Eskaera
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created
     *
     * @return DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set updated
     *
     * @param DateTime $updated
     *
     * @return Eskaera
     */
    public function setUpdated($updated)
    {
        $this->updated = $updated;

        return $this;
    }

    /**
     * Get updated
     *
     * @return DateTime
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * Set contentChangedBy
     *
     * @param string $contentChangedBy
     *
     * @return Eskaera
     */
    public function setContentChangedBy($contentChangedBy)
    {
        $this->contentChangedBy = $contentChangedBy;

        return $this;
    }

    /**
     * Get contentChangedBy
     *
     * @return string
     */
    public function getContentChangedBy()
    {
        return $this->contentChangedBy;
    }

    /**
     * Set user
     *
     * @param User $user
     *
     * @return Eskaera
     */
    public function setUser(User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set noiz
     *
     * @param DateTime $noiz
     *
     * @return Eskaera
     */
    public function setNoiz($noiz)
    {
        $this->noiz = $noiz;

        return $this;
    }

    /**
     * Get noiz
     *
     * @return DateTime
     */
    public function getNoiz()
    {
        return $this->noiz;
    }

    /**
     * Set type
     *
     * @param Type $type
     *
     * @return Eskaera
     */
    public function setType(Type $type = null)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return Type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set calendar
     *
     * @param Calendar $calendar
     *
     * @return Eskaera
     */
    public function setCalendar(Calendar $calendar = null)
    {
        $this->calendar = $calendar;

        return $this;
    }

    /**
     * Get calendar
     *
     * @return Calendar
     */
    public function getCalendar()
    {
        return $this->calendar;
    }

    /**
     * Set abiatua
     *
     * @param boolean $abiatua
     *
     * @return Eskaera
     */
    public function setAbiatua($abiatua)
    {
        $this->abiatua = $abiatua;

        return $this;
    }

    /**
     * Get abiatua
     *
     * @return boolean
     */
    public function getAbiatua()
    {
        return $this->abiatua;
    }

    /**
     * Set amaitua
     *
     * @param boolean $amaitua
     *
     * @return Eskaera
     */
    public function setAmaitua($amaitua)
    {
        $this->amaitua = $amaitua;

        return $this;
    }

    /**
     * Get amaitua
     *
     * @return boolean
     */
    public function getAmaitua()
    {
        return $this->amaitua;
    }

    /**
     * Set oharra
     *
     * @param string $oharra
     *
     * @return Eskaera
     */
    public function setOharra($oharra)
    {
        $this->oharra = $oharra;

        return $this;
    }

    /**
     * Get oharra
     *
     * @return string
     */
    public function getOharra()
    {
        return $this->oharra;
    }

    /**
     * Set sinatzaileak
     *
     * @param Sinatzaileak $sinatzaileak
     *
     * @return Eskaera
     */
    public function setSinatzaileak(Sinatzaileak $sinatzaileak = null)
    {
        $this->sinatzaileak = $sinatzaileak;

        return $this;
    }

    /**
     * Get sinatzaileak
     *
     * @return Sinatzaileak
     */
    public function getSinatzaileak()
    {
        return $this->sinatzaileak;
    }

    /**
     * Add firma
     *
     * @param Firma $firma
     *
     * @return Eskaera
     */
    public function addFirma(Firma $firma)
    {
        $this->firma[] = $firma;

        return $this;
    }

    /**
     * Remove firma
     *
     * @param Firma $firma
     */
    public function removeFirma(Firma $firma)
    {
        $this->firma->removeElement($firma);
    }

    /**
     * Get firma
     *
     * @return Collection
     */
    public function getFirma()
    {
        return $this->firma;
    }

    /**
     * Set firma
     *
     * @param Firma $firma
     *
     * @return Eskaera
     */
    public function setFirma(Firma $firma = null)
    {
        $this->firma = $firma;

        return $this;
    }

    /**
     * Add notification
     *
     * @param Notification $notification
     *
     * @return Eskaera
     */
    public function addNotification(Notification $notification)
    {
        $this->notifications[] = $notification;

        return $this;
    }

    /**
     * Remove notification
     *
     * @param Notification $notification
     */
    public function removeNotification(Notification $notification)
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
     * Set egutegian
     *
     * @param boolean $egutegian
     *
     * @return Eskaera
     */
    public function setEgutegian($egutegian)
    {
        $this->egutegian = $egutegian;

        return $this;
    }

    /**
     * Get egutegian
     *
     * @return boolean
     */
    public function getEgutegian()
    {
        return $this->egutegian;
    }

    /**
     * Set egunak
     *
     * @param string $egunak
     *
     * @return Eskaera
     */
    public function setEgunak($egunak)
    {
        $this->egunak = $egunak;

        return $this;
    }

    /**
     * Get egunak
     *
     * @return string
     */
    public function getEgunak()
    {
        return $this->egunak;
    }

    /**
     * Set total
     *
     * @param string $total
     *
     * @return Eskaera
     */
    public function setTotal($total)
    {
        $this->total = $total;

        return $this;
    }

    /**
     * Get total
     *
     * @return string
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * Add document
     *
     * @param Document $document
     *
     * @return Eskaera
     */
    public function addDocument(Document $document)
    {
        $this->documents[] = $document;

        return $this;
    }

    /**
     * Remove document
     *
     * @param Document $document
     */
    public function removeDocument(Document $document)
    {
        $this->documents->removeElement($document);
    }

    /**
     * Get documents
     *
     * @return Collection
     */
    public function getDocuments()
    {
        return $this->documents;
    }

    /**
     * Set bideratua.
     *
     * @param bool|null $bideratua
     *
     * @return Eskaera
     */
    public function setBideratua($bideratua = null)
    {
        $this->bideratua = $bideratua;

        return $this;
    }

    /**
     * Get bideratua.
     *
     * @return bool|null
     */
    public function getBideratua()
    {
        return $this->bideratua;
    }

    /**
     * Set konfliktoa.
     *
     * @param bool|null $konfliktoa
     *
     * @return Eskaera
     */
    public function setKonfliktoa($konfliktoa = null)
    {
        $this->konfliktoa = $konfliktoa;

        return $this;
    }

    /**
     * Get konfliktoa.
     *
     * @return bool|null
     */
    public function getKonfliktoa()
    {
        return $this->konfliktoa;
    }

    /**
     * Set emaitza.
     *
     * @param bool|null $emaitza
     *
     * @return Eskaera
     */
    public function setEmaitza($emaitza = null)
    {
        $this->emaitza = $emaitza;

        return $this;
    }

    /**
     * Get emaitza.
     *
     * @return bool|null
     */
    public function getEmaitza()
    {
        return $this->emaitza;
    }

    /**
     * Set nondik.
     *
     * @param string|null $nondik
     *
     * @return Eskaera
     */
    public function setNondik($nondik = null)
    {
        $this->nondik = $nondik;

        return $this;
    }

    /**
     * Get nondik.
     *
     * @return string|null
     */
    public function getNondik()
    {
        return $this->nondik;
    }

    /**
     * Set justifikatua.
     *
     * @param bool|null $justifikatua
     *
     * @return Eskaera
     */
    public function setJustifikatua($justifikatua = null)
    {
        $this->justifikatua = $justifikatua;

        return $this;
    }

    /**
     * Get justifikatua.
     *
     * @return bool|null
     */
    public function getJustifikatua()
    {
        return $this->justifikatua;
    }

    /**
     * Set lizentziamota.
     *
     * @param Lizentziamota|null $lizentziamota
     *
     * @return Eskaera
     */
    public function setLizentziamota(Lizentziamota $lizentziamota = null)
    {
        $this->lizentziamota = $lizentziamota;

        return $this;
    }

    /**
     * Get lizentziamota.
     *
     * @return Lizentziamota|null
     */
    public function getLizentziamota()
    {
        return $this->lizentziamota;
    }

    /**
     * Set kostua.
     *
     * @param string $kostua
     *
     * @return Eskaera
     */
    public function setKostua($kostua)
    {
        $this->kostua = $kostua;

        return $this;
    }

    /**
     * Get kostua.
     *
     * @return string
     */
    public function getKostua()
    {
        return $this->kostua;
    }

    /**
     * Set bertanbehera.
     *
     * @param bool|null $bertanbehera
     *
     * @return Eskaera
     */
    public function setBertanbehera($bertanbehera = null)
    {
        $this->bertanbehera = $bertanbehera;

        return $this;
    }

    /**
     * Get bertanbehera.
     *
     * @return bool|null
     */
    public function getBertanbehera()
    {
        return $this->bertanbehera;
    }

    /**
     * Set ordaindubeharda.
     *
     * @param bool|null $ordaindubeharda
     *
     * @return Eskaera
     */
    public function setOrdaindubeharda($ordaindubeharda = null)
    {
        $this->ordaindubeharda = $ordaindubeharda;

        return $this;
    }

    /**
     * Get ordaindubeharda.
     *
     * @return bool|null
     */
    public function getOrdaindubeharda()
    {
        return $this->ordaindubeharda;
    }

    /**
     * Set ordainduta.
     *
     * @param bool|null $ordainduta
     *
     * @return Eskaera
     */
    public function setOrdainduta($ordainduta = null)
    {
        $this->ordainduta = $ordainduta;

        return $this;
    }

    /**
     * Get ordainduta.
     *
     * @return bool|null
     */
    public function getOrdainduta()
    {
        return $this->ordainduta;
    }

    /**
     * Set sareko.
     *
     * @param bool|null $sareko
     *
     * @return Eskaera
     */
    public function setSareko($sareko = null)
    {
        $this->sareko = $sareko;

        return $this;
    }

    /**
     * Get sareko.
     *
     * @return bool|null
     */
    public function getSareko()
    {
        return $this->sareko;
    }

    /**
     * Set ikastaroaAmaituta.
     *
     * @param bool|null $ikastaroaAmaituta
     *
     * @return Eskaera
     */
    public function setIkastaroaAmaituta($ikastaroaAmaituta = null)
    {
        $this->ikastaroaAmaituta = $ikastaroaAmaituta;

        return $this;
    }

    /**
     * Get ikastaroaAmaituta.
     *
     * @return bool|null
     */
    public function getIkastaroaAmaituta()
    {
        return $this->ikastaroaAmaituta;
    }

    /**
     * Set erakundea.
     *
     * @param string $erakundea
     *
     * @return Eskaera
     */
    public function setErakundea($erakundea)
    {
        $this->erakundea = $erakundea;

        return $this;
    }

    /**
     * Get erakundea.
     *
     * @return string
     */
    public function getErakundea()
    {
        return $this->erakundea;
    }

    /**
     * Set aurreikusitakoOrdua.
     *
     * @param string|null $aurreikusitakoOrdua
     *
     * @return Eskaera
     */
    public function setAurreikusitakoOrdua($aurreikusitakoOrdua = null)
    {
        $this->aurreikusitakoOrdua = $aurreikusitakoOrdua;

        return $this;
    }

    /**
     * Get aurreikusitakoOrdua.
     *
     * @return string|null
     */
    public function getAurreikusitakoOrdua()
    {
        return $this->aurreikusitakoOrdua;
    }

    /**
     * Set aurreikusitakoIraupena.
     *
     * @param string|null $aurreikusitakoIraupena
     *
     * @return Eskaera
     */
    public function setAurreikusitakoIraupena($aurreikusitakoIraupena = null)
    {
        $this->aurreikusitakoIraupena = $aurreikusitakoIraupena;

        return $this;
    }

    /**
     * Get aurreikusitakoIraupena.
     *
     * @return string|null
     */
    public function getAurreikusitakoIraupena()
    {
        return $this->aurreikusitakoIraupena;
    }

    /**
     * Set non.
     *
     * @param string|null $non
     *
     * @return Eskaera
     */
    public function setNon($non = null)
    {
        $this->non = $non;

        return $this;
    }

    /**
     * Get non.
     *
     * @return string|null
     */
    public function getNon()
    {
        return $this->non;
    }
}
