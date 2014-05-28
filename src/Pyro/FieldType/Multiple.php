<?php namespace Pyro\FieldType;

use Pyro\Module\Streams\FieldType\FieldTypeAbstract;
use Pyro\Module\Streams\Stream\StreamModel;

/**
 * Class Multiple
 *
 * @package Pyro\FieldType
 * @author  AI Web Systems, Inc. - Ryan Thompson
 */
class Multiple extends FieldTypeAbstract
{
    /**
     * Field type slug
     *
     * @var string
     */
    public $field_type_slug = 'multiple';

    /**
     * DB column type
     *
     * @var string
     */
    public $db_col_type = false;

    /**
     * Alt process
     *
     * @var boolean
     */
    public $alt_process = true;

    /**
     * Custom parameters
     *
     * @var array
     */
    public $custom_parameters = array(
        'input_method',
        'relation_class',
    );

    /**
     * Version
     *
     * @var string
     */
    public $version = '2.0.0';

    /**
     * Author
     *
     * @var  array
     */
    public $author = array(
        'name' => 'Ryan Thompson - PyroCMS',
        'url'  => 'https://www.pyrocms.com/about/the-team'
    );

    /**
     * Relation
     *
     * @return null|\Pyro\Module\Streams\FieldType\Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function relation()
    {
        return $this->belongsToMany($this->getRelationClass(), $this->getTableName(), 'entry_id', 'related_id');
    }

    /**
     * Field event
     */
    public function fieldEvent()
    {
        if ($this->getParameter('use_ajax')) {
            $class = $this->getRelationClass();
            $model = new $class;

            $data = array(
                'value'          => $this->getRelationResult(),
                'jquerySelector' => $this->form_slug . '-selectize',
                'valueField'     => $model->getFieldTypeRelationshipValueField(),
                'searchFields'   => $model->getFieldTypeRelationshipSearchFields(),
                'itemTemplate'   => $model->getPresenter()->getFieldTypeRelationshipItemTemplate(),
                'optionTemplate' => $model->getPresenter()->getFieldTypeRelationshipOptionTemplate(),
                'relationClass'  => $this->getRelationClass(),
            );

            $this->appendMetadata($this->view('fragments/relationship.js.php', $data, true));
        }

        if ($this->getParameter('input_method') == 'checkbox') {

            $this->appendMetadata($this->view('fragments/checkbox.js.php', null, true));

        }
    }

    /**
     * Output form input
     *
     * @access     public
     * @return    string
     */
    public function formInput()
    {

        if ($this->getParameter('input_method') == 'checkbox') {

            return $this->formInputCheckbox();

        } else {

            $options = array(null => lang_label($this->getPlaceholder())) + $this->getOptions();

            if (!$this->getParameter('use_ajax')) {
                $attributes = '';
            } else {
                $attributes = 'class="' . $this->form_slug . '-selectize skip"';
            }

            return form_multiselect($this->form_slug . '[]', $options, $this->value, $attributes);

        }

    }

    /**
     * Form input checkbox
     * Assumes that we have a header, and individual sections
     *
     * @return string
     */
    public function formInputCheckbox()
    {

        $options  = $this->getOptions();
        $selected = $this->getRelationResult()->lists('id');
        $groups   = array();

        foreach ($options as $header => $section) {

            foreach ($section as $title => $values) {

                $output = '';

                foreach ($values as $value => $name) {


                    $checked = (in_array($value, $selected)) ? true : false;
                    $data    = array(
                        'name'    => $this->form_slug . '[]',
                        'id'      => $this->form_slug . '-' . $value,
                        'value'   => $value,
                        'checked' => $checked,
                        'style'   => 'margin:10px',
                        'data-header' => strtolower(url_title($header)),
                        'data-section' => strtolower(url_title($title))
                    );

                    $output .= form_checkbox($data) . form_label($name, $this->form_slug . '-' . $value) . '<br>';

                }

                $groups[$header][$title] = $output;

            }

        }

        $results = '';
        foreach ($groups as $header => $section) {

            $results .= '<div class="row p-b p-t">';
            $results .= '<div class="col-lg-12">';
            $results .= '<strong>' . $header . '</strong>';
            $results .= '<br><small class=""><a href="#" class="checkbox_header" data-type="all" data-header="'.strtolower(url_title($header)).'">All</a>';
            $results .= ' |  <a href="#" class="checkbox_header" data-type="none" data-header="'.strtolower(url_title($header)).'">None</a></small>';
            $results .= '</div>';
            $results .= '</div>';
            $results .= '<div class="row m-b">';

            foreach ($section as $name => $output) {
                $results .= '<div class="col-lg-3"><h5>' . $name . '</h5>';
                $results .= '<small class=""><a href="#" class="checkbox_header" data-type="all" data-section="'.strtolower(url_title($name)).'">All</a>';
                $results .= ' |  <a href="#" class="checkbox_header" data-type="none" data-section="'.strtolower(url_title($name)).'">None</a></small><br>';
                $results .=  $output;
                $results .= '</div>';
            }

            $results .= '</div>';

        }

        return $results;

    }


    /**
     * Output the form input for frontend use
     *
     * @return string
     */
    public function publicFormInput()
    {
        return form_dropdown($this->form_slug, $this->getOptions(), $this->value);
    }

    /**
     * Process before saving
     *
     * @return string
     */
    public function preSave()
    {
        $table = $this->getTableName();

        ci()->pdb->table($table)->where('entry_id', $this->entry->getKey())->delete();

        $insert = array();

        foreach ((array)ci()->input->post($this->form_slug) as $id) {
            if ($id) {
                $insert[] = array(
                    'entry_id'   => $this->entry->getKey(),
                    'related_id' => $id,
                );
            }
        }

        if (!empty($insert)) {
            ci()->pdb->table($table)->insert($insert);
        }

        ci()->cache->forget($this->getStream()->stream_namespace .".*");
    }

    /**
     * Output filter input
     *
     * @access     public
     * @return    string
     */
    public function filterInput()
    {
        $options = array(null => lang_label($this->getPlaceholder())) + $this->getOptions();

        return form_dropdown($this->getFilterSlug('is'), $options, $this->getFilterValue('is'));
    }

    /**
     * String output
     *
     * @return  mixed   null or string
     */
    public function stringOutput()
    {
        $model = $this->getParameter('relation_class');

        if ($collection = $this->getRelationResult() and $collection->count() and $model = new $model) {
            return implode(', ', $collection->lists($model->getTitleColumn()));
        }

        return null;
    }

    /**
     * Plugin output
     *
     * @return array
     */
    public function pluginOutput()
    {
        if ($collection = $this->getRelationResult()) {
            return $collection;
        }

        return null;
    }

    /**
     * Data output
     *
     * @return RelationClassModel
     */
    public function dataOutput()
    {
        return $this->pluginOutput();
    }

    /**
     * Choose a stream to relate to.. or remote source
     *
     * @param  mixed $value
     * @return string
     */
    public function paramStream($value = null)
    {
        $options = StreamModel::getStreamAssociativeOptions();

        return form_dropdown('stream', $options, $value);
    }

    /**
     * Options
     *
     * @return array
     */
    public function getOptions()
    {

        if (!$this->getParameter('use_ajax')) {
            if ($relatedClass = $this->getRelationClass()) {

                $relatedModel = new $relatedClass;

                if (!$relatedModel instanceof RelationshipInterface) {
                    throw new ClassNotInstanceOfRelationshipInterfaceException;
                }

                return $relatedModel->getFieldTypeRelationshipOptions($this);
            }
        }

        return array();
    }

    /**
     * Get column name
     *
     * @return string
     */
    public function getColumnName()
    {
        return parent::getColumnName() . '_id';
    }

    /**
     * Run this when the field gets assigned
     *
     * @return void
     */
    public function fieldAssignmentConstruct()
    {
        $instance = $this;

        $schema = ci()->pdb->getSchemaBuilder();

        $schema->dropIfExists($this->getTableName());

        $schema->create(
            $this->getTableName(),
            function ($table) use ($instance) {
                $table->integer('entry_id');
                $table->integer('related_id');
            }
        );
    }

    /**
     * Field assignment destruct
     */
    public function fieldAssignmentDestruct()
    {
        $schema = ci()->pdb->getSchemaBuilder();

        $schema->dropIfExists($this->getTableName());
    }

    /**
     * Namespace destruct
     */
    public function namespaceDestruct()
    {
        $this->fieldAssignmentConstruct();
    }

    /**
     * Get the table
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->getStream()->stream_prefix . $this->getStream()->stream_slug . '_' . $this->field->field_slug;
    }

    /**
     * Search
     *
     * @return string
     */
    public function ajaxSearch()
    {
        $class = ci()->input->post('relation_class');
        $model = new $class;
        $term  = urldecode(ci()->input->post('term'));

        echo $model->getFieldTypeRelationshipResults($term);
    }
}