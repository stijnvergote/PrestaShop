<?php
/**
 * 2007-2015 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2015 PrestaShop SA
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
namespace PrestaShopBundle\Form\Admin\Product;

use PrestaShopBundle\Form\Admin\Type\CommonAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use PrestaShopBundle\Form\Admin\Type as PsFormType;
use Symfony\Component\Form\Extension\Core\Type as FormType;

/**
 * This form class is responsible to generate the product quantity form
 */
class ProductQuantity extends CommonAbstractType
{
    private $router;
    private $translator;
    private $configuration;

    /**
     * Constructor
     *
     * @param object $translator
     * @param object $router
     * @param object $legacyContext
     */
    public function __construct($translator, $router, $legacyContext)
    {
        $this->router = $router;
        $this->translator = $translator;
        $this->legacyContext = $legacyContext;
        $this->locales = $this->legacyContext->getLanguages();
        $this->configuration = $this->getConfiguration();
    }

    /**
     * {@inheritdoc}
     *
     * Builds form
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('attributes', 'Symfony\Component\Form\Extension\Core\Type\TextType', array(
            'attr' =>  [
                'class' => 'tokenfield',
                'data-minLength' => 1,
                'placeholder' => $this->translator->trans('Combine several attributes, e.g.: "Size: all", "Color: red".', [], 'Admin.Catalog.Help'),
                'data-prefetch' => $this->router->generate('admin_attribute_get_all'),
                'data-action' => $this->router->generate('admin_attribute_generator'),
            ],
            'label' =>  $this->translator->trans('Create combinations', [], 'Admin.Catalog.Feature')
            ))
            ->add('advanced_stock_management', 'Symfony\Component\Form\Extension\Core\Type\CheckboxType', array(
                'required' => false,
                'label' => $this->translator->trans('I want to use the advanced stock management system for this product.', [], 'Admin.Catalog.Feature'),
            ))
            ->add('pack_stock_type', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', ['choices_as_values' => true, ]) //see eventListener for details
            ->add('depends_on_stock', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array(
                'choices'  => array(
                    $this->translator->trans('The available quantities for the current product and its combinations are based on the stock in your warehouse (using the advanced stock management system). ', [], 'Admin.Catalog.Feature') => 1,
                    $this->translator->trans('I want to specify available quantities manually.', [], 'Admin.Catalog.Feature') => 0,
                ),
                'choices_as_values' => true,
                'expanded' => true,
                'required' => true,
                'multiple' => false,
            ))
            ->add('qty_0', 'Symfony\Component\Form\Extension\Core\Type\NumberType', array(
                'required' => true,
                'label' => $this->translator->trans('Quantity', [], 'Admin.Catalog.Feature'),
                'constraints' => array(
                    new Assert\NotBlank(),
                    new Assert\Type(array('type' => 'numeric')),
                ),
            ))
            ->add('combinations', 'Symfony\Component\Form\Extension\Core\Type\CollectionType', array(
                'entry_type' =>'PrestaShopBundle\Form\Admin\Product\ProductCombination',
                'allow_add' => true,
                'allow_delete' => true
            ))
            ->add('out_of_stock', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array(
                'choices_as_values' => true,
            ))
            ->add('minimal_quantity', 'Symfony\Component\Form\Extension\Core\Type\NumberType', array(
                'required' => true,
                'label' => $this->translator->trans('Minimum quantity', [], 'Admin.Catalog.Feature'),
                'constraints' => array(
                    new Assert\NotBlank(),
                    new Assert\Type(array('type' => 'numeric')),
                ),
            ))
            ->add('available_now', 'PrestaShopBundle\Form\Admin\Type\TranslateType', array(
                'type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
                'options' => [],
                'locales' => $this->locales,
                'hideTabs' => true,
                'label' =>  $this->translator->trans('Label when in stock', [], 'Admin.Catalog.Feature')
            ))
            ->add('available_later', 'PrestaShopBundle\Form\Admin\Type\TranslateType', array(
                'type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
                'options' => [],
                'locales' => $this->locales,
                'hideTabs' => true,
                'label' =>  $this->translator->trans('Label when out of stock', [], 'Admin.Catalog.Feature')
            ))
            ->add('available_date', 'PrestaShopBundle\Form\Admin\Type\DatePickerType', array(
                'required' => false,
                'label' => $this->translator->trans('Availability date', [], 'Admin.Catalog.Feature'),
                'attr' => ['placeholder' => 'YYYY-MM-DD']
            ))
            ->add('virtual_product', 'PrestaShopBundle\Form\Admin\Product\ProductVirtual', array(
                'required' => false,
                'label' => $this->translator->trans('Does this product have an associated file?', [], 'Admin.Catalog.Feature'),
            ));

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();

            //Manage out_of_stock field with contextual values/label
            $defaultChoiceLabel = $this->translator->trans('Use default behavior', [], 'Admin.Catalog.Feature').' (';
            $defaultChoiceLabel .= $this->configuration->get('PS_ORDER_OUT_OF_STOCK') == 1 ?
                $this->translator->trans('Allow orders', [], 'Admin.Catalog.Feature') :
                $this->translator->trans('Deny orders', [], 'Admin.Catalog.Feature');
            $defaultChoiceLabel .= ')';

            $form->add('out_of_stock', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array(
                'choices'  => array(
                    $this->translator->trans('Deny orders', [], 'Admin.Catalog.Feature') => '0',
                    $this->translator->trans('Allow orders', [], 'Admin.Catalog.Feature') => '1',
                    $defaultChoiceLabel => '2',
                ),
                'choices_as_values' => true,
                'expanded' => true,
                'required' => false,
                'placeholder' => false,
                'label' => $this->translator->trans('When out of stock', [], 'Admin.Catalog.Feature')
            ));

            //Manage out_of_stock field with contextual values/label
            $pack_stock_type = $this->configuration->get('PS_PACK_STOCK_TYPE');
            $defaultChoiceLabel = $this->translator->trans('Default', [], 'Admin.Global').': ';
            if ($pack_stock_type == 0) {
                $defaultChoiceLabel .= $this->translator->trans('Decrement pack only.', [], 'Admin.Catalog.Feature');
            } elseif ($pack_stock_type == 1) {
                $defaultChoiceLabel .= $this->translator->trans('Decrement products in pack only.', [], 'Admin.Catalog.Feature');
            } else {
                $defaultChoiceLabel .= $this->translator->trans('Decrement both.', [], 'Admin.Catalog.Feature');
            }

            $form->add('pack_stock_type', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array(
                'choices'  => array(
                    $this->translator->trans('Decrement pack only.', [], 'Admin.Catalog.Feature') => '0',
                    $this->translator->trans('Decrement products in pack only.', [], 'Admin.Catalog.Feature') => '1',
                    $this->translator->trans('Decrement both.', [], 'Admin.Catalog.Feature') => '2',
                    $defaultChoiceLabel => '3',
                ),
                'choices_as_values' => true,
                'expanded' => false,
                'required' => true,
                'placeholder' => false,
                'label' => $this->translator->trans('Pack quantities', [], 'Admin.Catalog.Feature')
            ));
        });
    }

    /**
     * Returns the block prefix of this type.
     *
     * @return string The prefix name
     */
    public function getBlockPrefix()
    {
        return 'product_quantity';
    }
}