<?php
/**
 * @var \Pimcore\Templating\PhpEngine $this
 * @var \Pimcore\Templating\PhpEngine $view
 * @var \Pimcore\Templating\GlobalVariables $app
 */

$isValid = $form->vars['valid'];
?>

<div class="form-group <?= $isValid ? '' : 'has-error' ?>">
    <?= $this->form()->label($form) ?>
    <?= $this->form()->widget($form, [
        'attr' => [
            'class' => 'form-control'
        ]
    ]) ?>
</div>