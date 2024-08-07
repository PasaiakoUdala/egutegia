<?php

/*
 *     Iker Ibarguren <@ikerib>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;

class LogRepository extends EntityRepository
{
    public function findCalendarLogs($calendarid)
    {
        $em = $this->getEntityManager();
        /** @var $query \Doctrine\DBAL\Query\QueryBuilder */
        $query = $em->createQuery('
            SELECT l
                FROM AppBundle:Log l
                WHERE l.calendar = :calendarid
        ');

        //$consulta = $em->createQuery($dql);
        $query->setParameter('calendarid', $calendarid);

        return $query->getResult();
    }

    public function findLoginlogs()
    {
        $em = $this->getEntityManager();
        $sqlDate = new \DateTime();
        $sqlDate->modify('-30 days');

        /** @var $query \Doctrine\DBAL\Query\QueryBuilder */
        $query = $em->createQuery('
            SELECT l
                FROM AppBundle:Log l
                WHERE l.name = :textua
                AND l.created >= :date
        ');

        //$consulta = $em->createQuery($dql);
        $query->setParameter('textua', "Login");
        $query->setParameter('date', $sqlDate);

        return $query->getResult();
    }
}
