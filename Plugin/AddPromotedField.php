<?php
declare(strict_types=1);

namespace Tonyapps\Search\Plugin;

use Magento\Framework\Data\Form;
use Magento\Framework\Registry;
use Magento\Search\Block\Adminhtml\Term\Edit\Form as TermForm;

/** Adds "Promoted SKUs" to the core search term form; core Save already persists any posted column. */
class AddPromotedField
{
    public function __construct(private Registry $registry)
    {
    }

    public function beforeSetForm(TermForm $subject, Form $form): array
    {
        $fieldset = $form->getElement('base_fieldset');
        if ($fieldset && !$form->getElement('promoted_skus')) {
            $fieldset->addField('promoted_skus', 'textarea', [
                'name' => 'promoted_skus',
                'label' => __('Promoted SKUs'),
                'title' => __('Promoted SKUs'),
                'note' => __('Comma-separated. These products are shown first, in this order, even without the search term in their text. Visibility and filters still apply.'),
                'value' => $this->registry->registry('current_catalog_search')?->getData('promoted_skus'),
            ]);
        }
        return [$form];
    }
}
