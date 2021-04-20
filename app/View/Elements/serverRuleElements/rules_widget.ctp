<?php 
$seed = rand();
?>
<div>
    <div style="display: flex;" class="rules-widget-container container-seed-<?= $seed ?>" data-funname="initRuleWidgetPicker<?= $seed ?>">
        <div style="flex-grow: 1;">
            <div class="bold green" style="display: flex; align-items: center;">
                <?= __('Allowed %s (OR)', Inflector::pluralize($scopeI18n));?>
                <i
                    class="useCursorPointer <?= $this->FontAwesome->getClass('trash') ?>"
                    style="margin-left: auto;"
                    title="<?= __('Delete selected rules') ?>"
                    onClick="<?= sprintf("handleDeleteButtonClick('%s', this); ", 'rules-allow') ?>"
                ></i>
            </div>
            <select
                id="<?= sprintf('%s%sLeftValues', Inflector::pluralize($scope), $technique) ?>"
                size="6" multiple
                style="margin-bottom: 0;  width: 100%; overflow-x: auto;" class="rules-select-data rules-allow"
            ></select>
        </div>
        <div style="display: flex; margin: 0 0.5em; flex-shrink: 1; padding-top: 20px;">
            <div style="display: flex; flex-direction: column;">
                <div class="input-prepend input-append">
                    <button
                        class="btn"
                        type="button"
                        title="<?= __('Move %s to the list of %s to allow', $scopeI18n, Inflector::pluralize($scopeI18n));?>"
                        aria-label="<?= __('Move %s to the list of %s to allow', $scopeI18n, Inflector::pluralize($scopeI18n));?>"
                        role="button" tabindex="0"
                        onClick="<?= sprintf("handleFreetextButtonClick('%s', this); ", 'rules-allow') ?>"
                    >
                    <i class="<?= $this->FontAwesome->getClass('caret-left') ?>"></i>
                    </button>
                    <input type="text" style="" placeholder="<?= sprintf('Freetext %s name', $scopeI18n) ?>">
                    <button
                        class="btn"
                        type="button"
                        title="<?= __('Move %s to the list of %s to block', $scopeI18n, Inflector::pluralize($scopeI18n));?>"
                        aria-label="<?= __('Move %s to the list of %s to block', $scopeI18n, Inflector::pluralize($scopeI18n));?>"
                        role="button" tabindex="0"
                        onClick="<?= sprintf("handleFreetextButtonClick('%s', this); ", 'rules-block') ?>"
                    >
                        <i class="<?= $this->FontAwesome->getClass('caret-right') ?>"></i>
                    </button>
                </div>
                <?php if(!empty($options) || $allowEmptyOptions): ?>
                    <div class="input-prepend input-append">
                        <button
                            class="btn"
                            type="button"
                            title="<?= __('Move %s to the list of %s to allow', $scopeI18n, Inflector::pluralize($scopeI18n));?>"
                            aria-label="<?= __('Move %s to the list of %s to allow', $scopeI18n, Inflector::pluralize($scopeI18n));?>"
                            role="button" tabindex="0"
                            onClick="<?= sprintf("handlePickerButtonClick('%s', this); ", 'rules-allow') ?>"
                        >
                        <i class="<?= $this->FontAwesome->getClass('caret-left') ?>"></i>
                        </button>
                        <select
                            class="rules-select-picker rules-select-picker-<?= $scope ?>"
                            multiple
                            placeholder="<?= sprintf('%s name', $scopeI18n) ?>"
                        >
                            <?php foreach($options as $option): ?>
                                <?php if(is_array($option)): ?>
                                    <option value="<?= !empty($optionNoValue) ? h($option['name']) : h($option['id']) ?>"><?= h($option['name']) ?></option>
                                <?php else: ?>
                                    <option value="<?= h($option) ?>"><?= h($option) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <button
                            class="btn"
                            type="button"
                            title="<?= __('Move %s to the list of %s to block', $scopeI18n, Inflector::pluralize($scopeI18n));?>"
                            aria-label="<?= __('Move %s to the list of %s to block', $scopeI18n, Inflector::pluralize($scopeI18n));?>"
                            role="button" tabindex="0"
                            onClick="<?= sprintf("handlePickerButtonClick('%s', this); ", 'rules-block') ?>"
                        >
                            <i class="<?= $this->FontAwesome->getClass('caret-right') ?>"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div style="flex-grow: 1;">
            <div class="bold red" style="display: flex; align-items: center;">
                <?php echo __('Blocked %s (AND NOT)', Inflector::pluralize($scopeI18n));?>
                <i
                    class="useCursorPointer <?= $this->FontAwesome->getClass('trash') ?>"
                    style="margin-left: auto;"
                    title="<?= __('Delete selected rules') ?>"
                    onClick="<?= sprintf("handleDeleteButtonClick('%s', this); ", 'rules-block') ?>"
                ></i>
            </div>
            <select
                id="<?= sprintf('%s%sRightValues', Inflector::pluralize($scope), $technique) ?>"
                size="6" multiple
                style="margin-bottom: 0; width: 100%; overflow-x: auto;" class="rules-select-data rules-block"
            ></select>
        </div>
    </div>
</div>

<script>
function initRuleWidgetPicker<?= $seed ?>() {
    $('.container-seed-<?= $seed ?> select.rules-select-picker').chosen()
    $('.container-seed-<?= $seed ?> select.rules-select-data').keydown(function(evt) {
        var $select = $(this)
        var $pickerSelect = $select.closest('.rules-widget-container').find('select.rules-select-picker')
        if (evt.keyCode === 46) { // <DELETE>
            deleteSelectedRules($select, $pickerSelect)
        }
    });
}

function deleteSelectedRules($select, $pickerSelect) {
    $select.find(":selected").each(function() {
        var $item = $(this)
        if (!getValuesFromSelect($pickerSelect).includes($item.val())) {
            $pickerSelect.append($('<option/>', {
                value: $item.val(),
                text : $item.text()
            }))
        }
        $item.remove()
    })
    $pickerSelect.trigger('chosen:updated')
}

function handleDeleteButtonClick(targetClass, clicked) {
    var $select = $(clicked).closest('.rules-widget-container').find('select.' + targetClass)
    var $pickerSelect = $select.closest('.rules-widget-container').find('select.rules-select-picker')
    deleteSelectedRules($select, $pickerSelect)
}

function handleFreetextButtonClick(targetClass, clicked) {
    var $target = $(clicked).closest('.rules-widget-container').find('select.' + targetClass)
    var $input = $(clicked).parent().find('input');
    addItemToSelect($target, $input.val())
    $input.val('')
}

function handlePickerButtonClick(targetClass, clicked) {
    var $select = $(clicked).parent().find('select');
    var values = $select.val()
    $select.children().each(function() {
        if (values.includes($(this).val())) {
            var $target = $select.closest('.rules-widget-container').find('select.' + targetClass)
            moveItemToSelect($target, $(this))
        }
    });
    $select.trigger('chosen:updated')
}

function moveItemToSelect($target, $source) {
    if (!getValuesFromSelect($target).includes($source.val())) {
        $target.append($('<option/>', {
            value: $source.val(),
            text : $source.text()
        }));
    }
    $source.remove()
}

function addItemToSelect($target, data) {
    if (!getValuesFromSelect($target).includes(data)) {
        $target.append($('<option/>', {
            value: data,
            text : data
        }));
    }
}

function getValuesFromSelect($select) {
    var values = []
    $select.find('option').each(function() {
        values.push($(this).val())
    })
    return values
}
</script>

<style>
.rules-widget-container.container-seed-<?= $seed ?> .chosen-container .chosen-drop {
    width: fit-content;
    border-top: 1px solid #aaa;
}

.rules-widget-container.container-seed-<?= $seed ?> .chosen-container .search-choice > span {
    white-space: normal;
}
</style>