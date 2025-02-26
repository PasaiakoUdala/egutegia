<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use AppBundle\Entity\Lizentziamota;

class LizentziamotaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'name',
                null,
                array(
                    'label' => 'Mota',
                )
            )
            ->add(
                'sinatubehar',
                CheckboxType::class,
                array(
                    'required' => false,
                    'label' => 'Sinadura prozedura behar du?',
                )
            )
            ->add(
                'kostuabehar',
                CheckboxType::class,
                array(
                    'required' => false,
                    'label' => 'Kostua zehaztu behar da?',
                )
            )
            ->add('gaitu', CheckboxType::class, [
                'label' => 'Lizentzia bat egiterakoan erakutsi Bai / Ez',
                'required' => false
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => Lizentziamota::class,
            )
        );
    }

    /**
     *
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return '';
    }
}
