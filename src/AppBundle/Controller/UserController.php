<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Eskaera;
use AppBundle\Entity\Saila;
use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Tests\Encoder\PasswordEncoder;

/**
 * User controller.
 *
 * @Route("admin/user")
 */
class UserController extends Controller
{
    /**
     * @Route("/", name="admin_user_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $users = $em->getRepository('AppBundle:User')->findAll();


        return $this->render('user/index.html.twig', array(
            'users'         => $users,
        ));
    }

    /**
     * @Route("/langileak", name="admin_user_langileak")
     * @Method("GET")
     */
    public function langileakAction()
    {
        $em = $this->getDoctrine()->getManager();

        $users = $em->getRepository('AppBundle:User')->getAllAktibo();

        return $this->render('user/langileak.html.twig', array(
            'users'         => $users,
            'sailak' => $em->getRepository(Saila::class)->findAll(),
        ));
    }

    /**
     * @Route("/langileak/{id}/eskaerak", name="admin_user_eskaerak")
     * @Method("GET")
     */
    public function eskaerakction($id)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var User $user */
        $user = $em->getRepository('AppBundle:User')->find($id);
        /** @var Eskaera $eskaerak */
        $eskaerak = $em->getRepository(Eskaera::class)->findAllByUser($id);

        return $this->render('user/langile_eskaerak.html.twig', array(
            'user'      => $user,
            'eskaerak'  => $eskaerak
        ));
    }


    /**
     * @Route("/alta/{id}", name="admin_user_alta")
     * @Method("GET")
     */
    public function altaAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var User $user */
        $user = $em->getRepository('AppBundle:User')->find($id);
        $user->setAktibo(1);
        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('admin_user_index');
    }

    /**
     * @Route("/baja/{id}", name="admin_user_baja")
     * @Method("GET")
     */
    public function bajaAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var User $user */
        $user = $em->getRepository('AppBundle:User')->find($id);
        $user->setAktibo(0);
        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('admin_user_index');
    }

    /**
     * @Route("/sailburua/{sailaid}/{userid}", name="admin_user_sailburua")
     * @Method("GET")
     */
    public function sailburuaAction($sailaid, $userid)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var User $user */
        $user = $em->getRepository('AppBundle:User')->find($userid);
        if ( $user->getSailburua() ) {
            $user->setSailburua(null);
        } else {
            $user->setSailburua(1);
        }

        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('admin_saila_show', ['id' => $sailaid]);
    }

    /**
     * @Route("/toggle-active/{id}", name="admin_user_toggle_active")
     * @Method("POST")
     */
    public function toggleActiveAction(Request $request, $id)
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'message' => 'Only AJAX requests are allowed'], 400);
        }

        $em = $this->getDoctrine()->getManager();
        /** @var User $user */
        $user = $em->getRepository('AppBundle:User')->find($id);
        
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        try {
            // Toggle the active status
            $currentStatus = $user->getAktibo();
            $user->setAktibo(!$currentStatus);
            $em->persist($user);
            $em->flush();

            $newStatus = $user->getAktibo() ? 'Alta' : 'Baja';
            $message = 'Erabiltzailearen egoera aldatu da: ' . $newStatus;

            return new JsonResponse([
                'success' => true, 
                'message' => $message,
                'newStatus' => $newStatus,
                'isActive' => $user->getAktibo()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false, 
                'message' => 'Errorea erabiltzailearen egoera aldatzean: ' . $e->getMessage()
            ], 500);
        }
    }
}
