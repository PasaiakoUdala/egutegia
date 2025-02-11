<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Gutxienekoak;
use AppBundle\Entity\Saila;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saila controller.
 *
 * @Route("admin/saila")
 */
class SailaController extends Controller
{
    /**
     * Lists all saila entities.
     *
     * @Route("/", name="admin_saila_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $sailak = $em->getRepository('AppBundle:Saila')->findAll();
        $deleteForms = [];
        foreach ($sailak as $e) {
            /** @var Saila $e */
            $deleteForms[ $e->getId() ] = $this->createDeleteForm($e)->createView();
        }

        return $this->render('saila/index.html.twig', array(
            'sailak' => $sailak,
            'deleteForms' => $deleteForms,
        ));
    }



    /**
     * Creates a new saila entity.
     *
     * @Route("/new", name="admin_saila_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $saila = new Saila();
        $form = $this->createForm('AppBundle\Form\SailaType', $saila, [
            'action' => $this->generateUrl('admin_saila_new'),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($saila);
            $em->flush();

            return $this->redirectToRoute('admin_saila_show', array('id' => $saila->getId()));
        }

        return $this->render('saila/new.html.twig', array(
            'saila' => $saila,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a saila entity.
     *
     * @Route("/{id}", name="admin_saila_show")
     * @Method("GET")
     */
    public function showAction(Saila $saila)
    {
        $deleteForm = $this->createDeleteForm($saila);

        return $this->render('saila/show.html.twig', array(
            'saila' => $saila,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing saila entity.
     *
     * @Route("/{id}/edit", name="admin_saila_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Saila $saila)
    {
        $deleteForm = $this->createDeleteForm($saila);
        $editForm = $this->createForm('AppBundle\Form\SailaType', $saila, [
            'action' => $this->generateUrl('admin_saila_edit', ['id' => $saila->getId()]),
            'method' => 'POST',
        ]);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            /** @var User $user */
            foreach ($saila->getUsers() as $user) {
                $user->setSaila($saila);
                $this->getDoctrine()->getManager()->persist($user);
            }
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('admin_saila_show', array('id' => $saila->getId()));
        }

        return $this->render('saila/edit.html.twig', array(
            'saila' => $saila,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a saila entity.
     *
     * @Route("/{id}", name="admin_saila_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Saila $saila)
    {
        $form = $this->createDeleteForm($saila);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($saila);
            $em->flush();
        }

        return $this->redirectToRoute('admin_saila_index');
    }

    /**
     * Creates a form to delete a saila entity.
     *
     * @param Saila $saila The saila entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Saila $saila)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_saila_delete', array('id' => $saila->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    /**
     *
     * @Route("/removeuser/{id}/{userid}", name="admin_saila_remove_user")
     * @Method("GET")
     */
    public function removeUserAction(Saila $saila, $userid)
    {
        /** @var User $user */
        foreach ($saila->getUsers() as $user) {
            if ( $user->getId() == $userid ) {
                $em = $this->getDoctrine()->getManager();
                $user->setSaila(null);
                $em->persist($user);
                $em->flush();
            }
        }

        return $this->redirectToRoute('admin_saila_show', ['id' => $saila->getId()]);
    }
}
