<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Type;
use AppBundle\Form\TypeType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;use Symfony\Component\HttpFoundation\Request;

/**
 * Type controller.
 *
 * @Route("admin/type")
 */
class TypeController extends Controller
{
    /**
     * Lists all type entities.
     *
     * @Route("/", name="admin_type_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $types = $em->getRepository('AppBundle:Type')->findAll();

        $deleteForms = array();
        foreach ($types as $type) {
            $deleteForms[$type->getId()] = $this->createDeleteForm($type)->createView();
        }

        return $this->render('type/index.html.twig', array(
            'types' => $types,
            'deleteforms' => $deleteForms,
        ));
    }

    /**
     * Lists all type entities.
     *
     * @Route("/list", name="admin_type_list")
     * @Method("GET")
     */
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();

        $types = $em->getRepository('AppBundle:Type')->findAll();

        return $this->render('type/list.html.twig', array(
            'types' => $types
        ));
    }

    /**
     * Creates a new type entity.
     *
     * @Route("/new", name="admin_type_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $type = new Type();
        $form = $this->createForm(
            TypeType::class, $type, array(
            'action' => $this->generateUrl('admin_type_new'),
            'method' => 'POST',
        ));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($type);
            $em->flush();

            return $this->redirectToRoute('admin_type_index');
        }

        return $this->render('type/new.html.twig', array(
            'type' => $type,
            'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing type entity.
     *
     * @Route("/{id}/edit", name="admin_type_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Type $type)
    {
        $deleteForm = $this->createDeleteForm($type);
        $editForm = $this->createForm(
            TypeType::class, $type, array(
            'action' => $this->generateUrl('admin_type_edit', array('id' => $type->getId())),
            'method' => 'POST',
        ));
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('admin_type_index');
        }

        return $this->render('type/edit.html.twig', array(
            'type' => $type,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a type entity.
     *
     * @Route("/{id}", name="admin_type_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Type $type)
    {
        $form = $this->createDeleteForm($type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($type);
            $em->flush();
        }

        return $this->redirectToRoute('admin_type_index');
    }

    /**
     * Creates a form to delete a type entity.
     *
     * @param Type $type The type entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Type $type)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_type_delete', array('id' => $type->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}