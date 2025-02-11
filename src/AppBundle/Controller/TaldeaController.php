<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Taldea;
use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Taldea controller.
 *
 * @Route("admin/taldea")
 */
class TaldeaController extends Controller
{
    /**
     * @Route("/zinegotziak", name="admin_taldea_zinegotziak")
     * @Method("GET")
     */
    public function zinegotziakAction(): ?\Symfony\Component\HttpFoundation\Response
    {
        $em = $this->getDoctrine()->getManager();

        $taldeak = $em->getRepository('AppBundle:Taldea')->findAll();
        $zinegotziak = $em->getRepository('AppBundle:User')->findBy(['department' => 'Zinegotzia']);

        return $this->render('taldea/zinegotziak.html.twig', array(
            'taldeak' => $taldeak,
            'zinegotziak' => $zinegotziak,
        ));
    }

    /**
     * @Route("/zinegotzi/taldea/assign", name="admin_zinegotzi_taldea_assign")
     * @Method("POST")
     */
    public function assignTaldeaAction(Request $request): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['error' => 'Only AJAX calls are allowed'], 400);
        }

        $userId = $request->request->get('userId');
        $taldeaId = $request->request->get('taldeaId');
        $isChecked = $request->request->get('isChecked') === 'true';

        $em = $this->getDoctrine()->getManager();
        /** @var User $user */
        $user = $em->getRepository('AppBundle:User')->find($userId);
        $taldea = $em->getRepository('AppBundle:Taldea')->find($taldeaId);

        if (!$user || !$taldea) {
            return new JsonResponse(['error' => 'User or Saila not found'], 404);
        }

        if ($isChecked) {
            $user->addZinegotziTaldeak($taldea);
        } else {
            $user->removeZinegotziTaldeak($taldea);
        }

        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Lists all taldea entities.
     *
     * @Route("/", name="admin_taldea_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $taldeas = $em->getRepository('AppBundle:Taldea')->findAll();
        $deleteForms = [];
        foreach ($taldeas as $e) {
            /** @var Taldea $e */
            $deleteForms[ $e->getId() ] = $this->createDeleteForm($e)->createView();
        }
        return $this->render('taldea/index.html.twig', array(
            'taldeas' => $taldeas,
            'deleteForms' => $deleteForms,
        ));
    }

    /**
     * Creates a new taldea entity.
     *
     * @Route("/new", name="admin_taldea_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $taldea = new Taldea();
        $form = $this->createForm('AppBundle\Form\TaldeaType', $taldea, [
            'action' => $this->generateUrl('admin_taldea_new'),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($taldea);
            $em->flush();

            return $this->redirectToRoute('admin_taldea_show', array('id' => $taldea->getId()));
        }

        return $this->render('taldea/new.html.twig', array(
            'taldea' => $taldea,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a taldea entity.
     *
     * @Route("/{id}", name="admin_taldea_show")
     * @Method("GET")
     */
    public function showAction(Taldea $taldea): ?\Symfony\Component\HttpFoundation\Response
    {
        $deleteForm = $this->createDeleteForm($taldea);

        return $this->render('taldea/show.html.twig', array(
            'taldea' => $taldea,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing taldea entity.
     *
     * @Route("/{id}/edit", name="admin_taldea_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Taldea $taldea)
    {
        $deleteForm = $this->createDeleteForm($taldea);
        $editForm = $this->createForm('AppBundle\Form\TaldeaType', $taldea, [
            'action' => $this->generateUrl('admin_taldea_edit', ['id' => $taldea->getId()]),
            'method' => 'POST',
        ]);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            /** @var User $user */
            foreach ($taldea->getUsers() as $user) {
                $user->setTaldea($taldea);
                $this->getDoctrine()->getManager()->persist($user);
            }
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('admin_taldea_show', array('id' => $taldea->getId()));
        }

        return $this->render('taldea/edit.html.twig', array(
            'taldea' => $taldea,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a taldea entity.
     *
     * @Route("/{id}/delete", name="admin_taldea_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Taldea $taldea)
    {
        $form = $this->createDeleteForm($taldea);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($taldea);
            $em->flush();
        }

        return $this->redirectToRoute('admin_taldea_index');
    }

    /**
     * Creates a form to delete a taldea entity.
     *
     * @param Taldea $taldea The taldea entity
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    private function createDeleteForm(Taldea $taldea)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_taldea_delete', array('id' => $taldea->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    /**
     *
     * @Route("/removeuser/{id}/{userid}", name="admin_taldea_remove_user")
     * @Method("GET")
     */
    public function removeUserAction(Taldea $taldea, $userid)
    {
        /** @var User $user */
        foreach ($taldea->getUsers() as $user) {
            if ( $user->getId() == $userid ) {
                $em = $this->getDoctrine()->getManager();
                $user->setTaldea(null);
                $em->persist($user);
                $em->flush();
            }
        }

        return $this->redirectToRoute('admin_taldea_show', ['id' => $taldea->getId()]);
    }
}
