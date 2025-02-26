<?php

namespace AppBundle\Form;

use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use AppBundle\Entity\Message;

class MessageType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder
            ->add('user', null, [
                'label' => 'Nori:',
                'required' => false,
                'multiple' => true,
                'mapped' => false,
                'attr' => [
                    'multiple' => true,
                    'class' => 'select2'
                ]
            ])
            ->add('name', null, [
                'label' => 'Gaia'
            ])
            ->add('description', CKEditorType::class, [
                'config_name' => 'simple_config',
                'label' => 'Mezua'
            ])
        ;
    }/**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefaults(array(
            'data_class' => Message::class,
            'csrf_protection' => false
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'appbundle_message';
    }
}
