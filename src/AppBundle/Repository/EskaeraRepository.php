<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * EskaeraRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class EskaeraRepository extends EntityRepository
{
    /**
     * @var
     */
    protected $lizentziaType;

    public function setLizentziaType($lizentziaType): void
    {
        $this->lizentziaType = $lizentziaType;
    }

    public function listAll($bertanbehera)
    {
        $qb = $this->_em->createQueryBuilder()
            ->select('e, m, u, c, f, t, s')
            ->from('AppBundle:Eskaera', 'e')
            ->leftJoin('e.type', 't')
            ->leftJoin('e.lizentziamota', 'm')
            ->leftJoin('e.user', 'u')
            ->leftJoin('e.calendar', 'c')
            ->leftJoin('e.firma', 'f')
            ->leftJoin('e.sinatzaileak', 's')
            ;

        if ('1' === $bertanbehera) {
            $qb->andWhere('e.bertanbehera != 1');
        }


        return $qb->getQuery()->getResult();
    }

    public function list($q, $history, $lm, $bertanbehera)
    {
        $em  = $this->getEntityManager();
        $dql = '';
        $qb  = '';

        switch ($q) {
            case 'no-way':
                $qb = $this->_em->createQueryBuilder()
                                ->select('e, t, s')
                                ->from('AppBundle:Eskaera', 'e')
                                ->innerJoin('e.type', 't')
                                ->leftJoin('e.sinatzaileak', 's')
                                ->where('e.abiatua=0');
                break;
            case 'unsigned':
                $qb = $this->_em->createQueryBuilder()
                                ->select('e,t,s')
                                ->from('AppBundle:Eskaera', 'e')
                                ->innerJoin('e.type', 't')
                                ->leftJoin('e.sinatzaileak', 's')
                                ->where('e.amaitua=0');
                break;
            case 'unadded':
                $qb = $this->_em->createQueryBuilder()
                                ->select('e,t,s')
                                ->from('AppBundle:Eskaera', 'e')
                                ->innerJoin('e.type', 't')
                                ->leftJoin('e.sinatzaileak', 's')
                                ->where('e.egutegian=0')->andWhere('e.amaitua=1')->andWhere('e.emaitza=1');
                break;
            case 'not-approved':
                $qb = $this->_em->createQueryBuilder()
                    ->select('e,t,s')
                    ->from('AppBundle:Eskaera', 'e')
                    ->innerJoin('e.type', 't')
                    ->leftJoin('e.sinatzaileak', 's')
                    ->where('e.egutegian=0')->andWhere('e.amaitua=1')->andWhere('e.emaitza=0');
                break;
            case 'conflict':
                $qb = $this->_em->createQueryBuilder()
                                ->select('e,t,s')
                                ->from('AppBundle:Eskaera', 'e')
                                ->innerJoin('e.type', 't')
                                ->leftJoin('e.sinatzaileak', 's')
                                ->where('e.egutegian=0')->andWhere('e.amaitua=1')->andWhere('e.bideratua=0')->andWhere('e.konfliktoa=1');
                break;
            case 'justify':
                if (null === $lm) {
                    $lm = '*';
                }

                $qb = $this->_em->createQueryBuilder()
                                ->select('e,t, s, lm')
                                ->from('AppBundle:Eskaera', 'e')
                                ->innerJoin('e.type', 't')
                                ->leftJoin('e.lizentziamota', 'lm')
                                ->leftJoin('e.sinatzaileak', 's')
                                ->where('t.id = :lizentzia_type')->setParameter('lizentzia_type', $this->lizentziaType)
                                ->andWhere('lm.id = :lizentzia_mota')->setParameter('lizentzia_mota', $lm);
                break;
            case 'nojustified':
                $qb = $this->_em->createQueryBuilder()
                                ->select('e,t,s')
                                ->from('AppBundle:Eskaera', 'e')
                                ->innerJoin('e.type', 't')
                                ->leftJoin('e.sinatzaileak', 's')
                                ->where('t.id = :lizentzia_type')->setParameter('lizentzia_type', $this->lizentziaType)->andWhere('e.justifikatua=0');
                break;
            default:
                $qb = $this->_em->createQueryBuilder()
                                ->select('e,t,s,c,tm,u,f')
                                ->from('AppBundle:Eskaera', 'e')
                                ->innerJoin('e.type', 't')
                                ->leftJoin('e.sinatzaileak', 's')
                                ->innerJoin('e.calendar', 'c')
                                ->leftJoin('e.firma', 'f')
                                ->innerJoin('c.template', 'tm')
                                ->innerJoin('e.user', 'u')
                                ;
                break;
        }

        if ('0' === $history) {
            $currentYEAR = date('Y');
            $qb->innerJoin('e.calendar', 'cc')->andWhere('cc.year = :year')->setParameter('year', $currentYEAR);
        }
        if ('1' === $bertanbehera) {
            $qb->andWhere('e.bertanbehera != 1');
        }

        return $qb->getQuery()->getResult();
    }

    public function findAllByUser($id)
    {
        $em = $this->getEntityManager();

        $dql = '
            SELECT e
            FROM AppBundle:Eskaera e
              INNER JOIN e.user u
            WHERE u.id = :id
            ORDER BY e.created DESC

        ';

        $query = $em->createQuery($dql);
        $query->setParameter('id', $id);

        return $query->getResult();
    }

    public function findBideratugabeak()
    {
        $em = $this->getEntityManager();
        $dql = '
            SELECT e
            FROM AppBundle:Eskaera e
            WHERE e.abiatua = false AND e.amaitua = false
        ';
        $query = $em->createQuery($dql);

        return $query->getResult();
    }

    public function checkErabiltzaileaBateraezinZerrendan($userid)
    {
        $qb = $this->_em->createQueryBuilder()
            ->select('g')

            ->from('AppBundle:Gutxienekoak', 'g')
            ->innerJoin('g.gutxienekoakdet', 'gd')
            ->innerJoin('gd.user', 'u')

            ->where('u.id = :userid')

            ->setParameter('userid', $userid)
            ;

        return $qb->getQuery()->getResult();
    }

    public function checkCollision($userid, $fini, $ffin)
    {
        $qb = $this->createQueryBuilder('e');

        $qb->innerJoin('e.calendar', 'c')
           ->innerJoin('c.user', 'u')
           ->where('u.id=:userid')
           ->andWhere('(:fini BETWEEN e.hasi AND e.amaitu) OR (:ffin BETWEEN e.hasi AND e.amaitu)')
           ->setParameter('userid', $userid)
           ->setParameter('fini', $fini)
           ->setParameter('ffin', $ffin)
        ;

        return $qb->getQuery()->getResult();
    }

    public function findAbsentismo($fini, $ffin)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->where('(:fini BETWEEN e.hasi AND e.amaitu) OR (:ffin BETWEEN e.hasi AND e.amaitu)')
            ->setParameter('fini', $fini)
            ->setParameter('ffin', $ffin)
        ;
        return $qb->getQuery()->getResult();
    }

    public function getAllBySailaId($sailaid)
    {
        $qm = $this->createQueryBuilder('e')
            ->innerJoin('e.user', 'u')
            ->innerJoin('u.saila', 's')
            ->andWhere('s.id = :sailaid')->setParameter('sailaid', $sailaid)
            ->orderBy('e.id', 'DESC')
        ;

        return $qm->getQuery()->getResult();
    }

    public function getAllBySaila($sailIzena)
    {
        $qm = $this->createQueryBuilder('e')
            ->innerJoin('e.user', 'u')
            ->andWhere('u.ldapsaila=:sailizena')->setParameter('sailizena', $sailIzena)
            ->orWhere('u.department=:sailizena')->setParameter('sailizena', $sailIzena)
            ;

        return $qm->getQuery()->getResult();
    }

    public function getLastBideratu($id)
    {
        $qm = $this->createQueryBuilder('e')
            ->innerJoin('e.sinatzaileak', 's')
            ->innerJoin('e.user', 'u')
            ->andWhere('u.id = :id')->setParameter('id', $id)
            ->orderBy('e.id','DESC')
            ->setMaxResults(1)
        ;

        return $qm->getQuery()->getResult();

    }

    public function getUserYearEvents($userid, $year) {
        $start = $year . "-01-01";
        ++$year;
        $end = $year . "-01-06";
        $qb = $this->createQueryBuilder('e')
            ->select('e,c,u')
                ->innerJoin('e.calendar', 'c')
                ->innerJoin('c.user', 'u')
            ->andWhere('e.hasi between :start and :end')->setParameter('start', $start)->setParameter('end', $end)
            ->andWhere('u.id = :userid')->setParameter('userid', $userid)
            ->andWhere('e.emaitza!=0')
            ->andWhere('e.bertanbehera != 1')
            ->orderBy('e.amaitu','ASC')
        ;

        $sql = $qb->getQuery()->getSQL();
        return $qb->getQuery()->getResult();
    }

    public function findIkastaroak($type_ikastaro_id, $sailaid =null)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->innerJoin('e.type', 't')
            ->andWhere('t.id = :type_ikastaro_id')->setParameter('type_ikastaro_id', $type_ikastaro_id)
        ;

        if($sailaid != null) {
            $qb->innerJoin('e.user', 'u')
                ->innerJoin('u.saila', 's')
                ->andWhere('s.id = :sailaid')->setParameter('sailaid', $sailaid);
        }

        $qb->orderBy('e.id', 'DESC');
        return $qb->getQuery()->getResult();
    }
}
