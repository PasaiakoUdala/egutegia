<?php

/*
 *     Iker Ibarguren <@ikerib>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AppBundle\Form;


use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TypeType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('labur',null,[
                'label' => 'Kuadranterako izen laburra:'
            ])
            ->add(
                'description',
                CKEditorType::class,
                ['config_name' => 'simple_config']
            )
            ->add('hours')
            ->add('color', null, [
                'label' => 'Kolorea',
            ])
            ->add('orden')
            /*
             * Eremu honen bitartez esaten diogu orduak zehaztean egutegian
             * Zein eremutatik kendu behar dituen orduak
             **/
            ->add('related', ChoiceType::class, [
                'required' => false,
                'label' => 'Erlazionatua',
                'placeholder' => 'Aukeratu bat',
                'choices' => [
                    'Jai Egunak' => 'hours_free',
                    'Norberarentzakoak' => 'hours_self',
                    'Konpentsatuak' => 'hours_compensed',
                    'Sindikalak' => 'hours_sindical',
                ],
            ])
            ->add('erakutsi', CheckboxType::class, [
                'required' => false,
                'label' => 'Erakutsi',
                'translation_domain' => 'messages',
            ])
            ->add('erakutsiEskaera', CheckboxType::class, [
                'required' => false,
                'label' => 'Erakutsi eskaeretan',
                'translation_domain' => 'messages',
            ])
            ->add('erakutsiOrdua', CheckboxType::class, [
                'required' => false,
                'label' => 'Eskaeretan Ordua botoia erakutsi Bai/ez',
                'translation_domain' => 'messages',
            ])
            ->add('erakutsiEguna', CheckboxType::class, [
                'required' => false,
                'label' => 'Eskaeretan Eguna botoia erakutsi Bai/ez',
                'translation_domain' => 'messages',
            ])
            ->add('lizentziamotabehar', CheckboxType::class, [
                'required' => false,
                'label' => 'Lizentzia Motak',
                'translation_domain' => 'messages',
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\Type',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_type';
    }
}
