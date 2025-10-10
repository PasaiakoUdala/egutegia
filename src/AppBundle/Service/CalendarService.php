<?php
/**
 * Created by PhpStorm.
 * User: iibarguren
 * Date: 11/8/18
 * Time: 2:23 PM
 */

namespace AppBundle\Service;

use AppBundle\Entity\Calendar;
use AppBundle\Entity\Event;
use AppBundle\Entity\Type;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManager;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class CalendarService
{
    protected $u;
    protected $em;

    public function __construct(EntityManager $em, TokenStorage $tokenStorage)
    {
        $this->em = $em;
        $this->u = $tokenStorage->getToken()->getUser();
    }

    /**
     * @param $datuak
     *               calendar_id (required) [int]
     *               type_id (required)  [int]
     *               event_name (required)  [string]
     *               event_start (required)  [datetime]
     *               event_fin (required) [datetime]
     *               event_hours (required) [float]
     *               event_nondik
     *               event_hours_self_before
     *               event_hours_self_half_before
     *
     * @return array
     * @throws \Doctrine\ORM\ORMException
     */
    public function addEvent($datuak): array
    {
        /** @var Calendar $calendar */
        $calendar = $this->em->getRepository('AppBundle:Calendar')->find($datuak['calendar_id']);
        /** @var Type $type */
        $type = $this->em->getRepository('AppBundle:Type')->find($datuak['type_id']);
        /** @var Event $event */
        $event = new Event();

        $event->setCalendar( $calendar );
        $event->setName($datuak[ 'event_name' ]);
        $event->setStartDate($datuak[ 'event_start' ]);
        $event->setEndDate($datuak[ 'event_fin' ]);
        $event->setHours($datuak[ 'event_hours' ]);
        $event->setType($type);

        if (array_key_exists('event_nondik', $datuak) && array_key_exists('event_hours_self_before', $datuak) && array_key_exists(
                'event_hours_self_half_before',
                $datuak
            ) && $type->getId() === 5) {
                $event->setNondik( $datuak[ 'event_nondik' ] );
                $event->setHoursSelfBefore( $datuak[ 'event_hours_self_before' ] );
                $event->setHoursSelfHalfBefore( $datuak[ 'event_hours_self_half_before' ] );
        }

        $this->em->persist($event);

        if ( $type->getRelated() ) {
            /** @var Type $t */
            $t = $event->getType();
            if ( $t->getRelated() === 'hours_free' ) {
                $calendar->setHoursFree( (float)$calendar->getHoursFree() - (float)$datuak[ 'event_hours' ]);
            }
            if ( $t->getRelated() === 'hours_self' ) {

                /**
                 * 1-. Begiratu eskatuta orduak jornada bat baino gehiago direla edo berdin,
                 * horrela bada hours_self-etik kendu bestela ordueta hours_self_half
                 */
                $jornada = floatval( $calendar->getHoursDay() );
                $orduak  = floatval( $datuak[ 'event_hours' ] );
                $nondik  = $datuak[ 'event_nondik' ];

                $partziala           = 0;
                $egunOsoaOrduak      = 0;
                $egutegiaOrduakTotal = floatval( $calendar->getHoursSelf() ) + floatval( $calendar->getHoursSelfHalf() );


                if ( $nondik === 'orduak') {
                    // Begiratu nahiko ordu dituen
                    if ( $calendar->getHoursSelfHalf() >= $orduak ) {
                        $partziala = $calendar->getHoursSelfHalf();
                    } else {
                        $resp = array(
                            'result' => -1,
                            'text'   => 'Ez ditu nahikoa ordu.',
                        );

                        return $resp;
                    }

                } else {
                    // Begiratu nahiko ordu dituen Egunetan
                    // Eskatutako ordu adina edo gehiago baditu
                    if ( $calendar->getHoursSelf() >= $orduak ) {
                        $calendar->setHoursSelf( $calendar->getHoursSelf() - $orduak );
                        $calendar->setHoursSelfHalf( $calendar->getHoursSelfHalf() - $orduak );
                    } else if ( $egutegiaOrduakTotal >= $orduak ) {
                        $zenbatEgun = $orduak / $jornada;
                        // Egun osoen kenketa
                        $egunOsoak = (int)$zenbatEgun;
                        // Orduen kenketa
                        $gainontzekoa = $zenbatEgun - (int)$zenbatEgun;

                        $egunOsoaOrduak = $egunOsoak * $jornada;
                        $partziala      = $gainontzekoa * $jornada;

                        $calendar->setHoursSelf( $calendar->getHoursSelf() - $egunOsoaOrduak );
                        $calendar->setHoursSelfHalf( $calendar->getHoursSelfHalf() - $partziala );
                    }
                }


            }
            if ( $t->getRelated() === 'hours_compensed' ) {
                $calendar->setHoursCompensed(
                    (float)$calendar->getHoursCompensed() - (float)$datuak[ 'event_hours' ]
                );
            }
            if ( $t->getRelated() === 'hours_sindical' ) {
                $calendar->setHoursSindikal(
                    (float)$calendar->getHoursSindikal() - (float)$datuak[ 'event_hours' ]
                );
            }
            $this->em->persist( $calendar );
        }

        $this->em->flush();

        return array(
            'result'=> 1,
            'id' => $event->getId()
        );
    }


    public function deleteEvent($id)
    {
        $event = $this->em->getRepository('AppBundle:Event')->find($id);

        if (null === $event) {
            return new View('Event ez da aurkitu', Response::HTTP_NOT_FOUND);
        }

        /** @var Calendar $calendar */
        $calendar = $event->getCalendar();

        /** @var Type $type */
        $type = $event->getType();
        if ($type->getRelated()) {
            /** @var Type $t */
            $t = $event->getType();
            if ('hours_free' === $t->getRelated()) {
                $calendar->setHoursFree((float)($calendar->getHoursFree()) + $event->getHours());
            }
            if ('hours_self' === $t->getRelated()) {
                /* Maiatzean (2018) Event entitarean sortu aurretik zuten balioak gordetzen hasi nintzen
                   ezabatzen denean, datu horiek berreskuratu ahal izateko. Baina aurretik grabatutako datuetan... kalkuluak egin behar
                 */
                if (null !== $event->getNondik()) {  // Aurreko egoerako datuak grabatuak daude, iuju!
                    if ('Egunak' === $event->getNondik()) {
                        $calendar->setHoursSelf((float)($calendar->getHoursSelf()) + (float)($event->getHours()));
                    } else {
                        $calendar->setHoursSelfHalf((float)($calendar->getHoursSelfHalf()) + (float)($event->getHours()));
                    }
                } else { // Kalkuluak egin behar. 2019rako egutegirako datorren elseko kodea ezaba daiteke, event guztiek izango bait dituzte datuak
                    $jornada = (float)($calendar->getHoursDay());
                    $orduak  = (float)($event->getHours());
                    if ($orduak < $jornada) {
                        $osoa      = $orduak;
                        $partziala = $orduak;
                    } else {
                        $zenbatEgun = $orduak / $jornada;

                        $orduOsoak = $jornada * $zenbatEgun;
                        $osoa      = $orduak;
                        $partziala = $orduak - $orduOsoak;
                    }
                    $calendar->setHoursSelf((float)($calendar->getHoursSelf()) + (float)$osoa * - 1);
                    $calendar->setHoursSelfHalf((float)($calendar->getHoursSelfHalf()) + (float)$partziala * - 1);
                }
            }
            if ('hours_compensed' === $t->getRelated()) {
                $calendar->setHoursCompensed((float)($calendar->getHoursCompensed()) + $event->getHours());
            }
            if ('hours_sindical' === $t->getRelated()) {
                $calendar->setHoursSindikal((float)($calendar->getHoursSindikal()) + $event->getHours());
            }
            $this->em->persist($calendar);
        }

        $this->em->remove($event);
        $this->em->flush();

        return $event;
    }
}
