<?php
declare(strict_types=1);

namespace Tonyapps\Search\Test\Unit\Plugin;

use Magento\Framework\DataObject;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\Form\Element\Fieldset;
use Magento\Framework\Registry;
use Magento\Search\Block\Adminhtml\Term\Edit\Form as TermForm;
use PHPUnit\Framework\TestCase;
use Tonyapps\Search\Plugin\AddPromotedField;

class AddPromotedFieldTest extends TestCase
{
    public function testAddsPromotedSkuFieldWithCurrentSearchTermValue(): void
    {
        $fieldset = $this->createMock(Fieldset::class);
        $fieldset->expects(self::once())->method('addField')->with(
            'promoted_skus',
            'textarea',
            self::callback(static fn(array $config): bool => $config['name'] === 'promoted_skus'
                && (string)$config['value'] === 'SKU-1,SKU-2'
                && isset($config['label'], $config['note']))
        );
        $form = $this->createMock(Form::class);
        $form->method('getElement')->willReturnCallback(static fn(string $name) => $name === 'base_fieldset' ? $fieldset : null);
        $currentTerm = new DataObject(['promoted_skus' => 'SKU-1,SKU-2']);
        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_catalog_search')->willReturn($currentTerm);

        $result = (new AddPromotedField($registry))->beforeSetForm($this->createMock(TermForm::class), $form);

        self::assertSame([$form], $result);
    }

    public function testDoesNothingWhenCoreFormHasNoBaseFieldset(): void
    {
        $form = $this->createMock(Form::class);
        $form->method('getElement')->willReturn(null);
        $registry = $this->createMock(Registry::class);
        $registry->expects(self::never())->method('registry');

        self::assertSame([$form], (new AddPromotedField($registry))->beforeSetForm($this->createMock(TermForm::class), $form));
    }
}
