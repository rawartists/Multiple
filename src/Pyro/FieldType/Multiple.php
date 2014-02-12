<?php namespace Pyro\FieldType;

use Pyro\Model\Eloquent;
use Pyro\Module\Streams\FieldType\FieldTypeAbstract;
use Pyro\Module\Streams\Field\FieldModel;
use Pyro\Module\Streams\Stream\StreamModel;

/**
 * PyroStreams Multiple Field Type
 *
 * @package        PyroCMS\Core\Modules\Streams Core\Field Types
 * @author        Parse19
 * @copyright    Copyright (c) 2011 - 2012, Parse19
 * @license        http://parse19.com/pyrostreams/docs/license
 * @link        http://parse19.com/pyrostreams
 */
class Multiple extends FieldTypeAbstract
{
    /**
     * Field type slug
     * @var string
     */
    public $field_type_slug = 'multiple';

    /**
     * DB column type
     * @var string
     */
    public $db_col_type = 'integer';

    /**
     * Alternative processing
     * Because we save to a pivot table
     * @var boolean
     */
    public $alt_process = true;

    /**
     * Custom parameters
     * @var array
     */
    public $custom_parameters = array(
        'stream',
        'max_selections',
        'search_columns',
        'placeholder',
        'option_format',
        'label_format',
        'method',
        'relation_class',
        );

    /**
     * Version
     * @var string
     */
    public $version = '2.0';

    /**
     * Author
     * @var  array
     */
    public $author = array(
        'name' => 'Ryan Thompson - PyroCMS',
        'url' => 'http://pyrocms.com/'
        );

    /**
     * Relation
     * @return object The relation object
     */
    public function relation()
    {
        return $this->belongsToMany($this->getRelationClass(), $this->getTableName(), 'entry_id', 'related_id');
    }

    /**
     * Fired when form is built per field
     * @return void
     */
    public function fieldEvent()
    {
        // Get related entries
        $entries = $this->getRelationResult();

        // Basically the selectize config mkay?
        $this->appendMetadata(
            $this->view(
                'data/multiple.js.php',
                array(
                    'relatedModel' => $this->getRelationClass(),
                    'fieldType' => $this,
                    'entries' => $entries,
                    ),
                true
                )
            );
    }

    /**
     * Fired when filters are built per field
     * @return void
     */
    public function filterFieldEvent()
    {
        // Set the value
        $this->setValue(ci()->input->get($this->getFilterSlug('is')));
        
        // Get related entries
        $relatedModel = $this->getRelationClass();

        // Get it
        if ($ids = $this->getValueIds()) {
            $entries = $relatedModel::select('*')->whereIn('id', $ids)->get();
        } else {
            $entries = null;
        }

        // Basically the selectize config mkay?
        $this->appendMetadata(
            $this->view(
                'data/multiple.js.php',
                array(
                    'relatedModel' => $this->getRelationClass(),
                    'fieldType' => $this,
                    'entries' => $entries,
                    ),
                true
                )
            );
    }

    /**
     * Output form input
     *
     * @access     public
     * @return    string
     */
    public function formInput()
    {
        // Attribtues
        $attributes = array(
            'class' => $this->form_slug.'-selectize skip',
            'placeholder' => $this->getParameter('placeholder', lang('streams:relationship.placeholder')),
            );

        // String em up
        $attribute_string = '';

        foreach ($attributes as $attribute => $value) {
            $attribute_string .= $attribute.'="'.$value.'" ';
        }

        // Return an HTML dropdown
        return form_dropdown($this->form_slug.'[]', array(), null, $attribute_string);
    }

    /**
     * Output the form input for frontend use
     * @return string 
     */
    public function publicFormInput()
    {
        // TODO
        return 'Multiple publidFormInput() needed';
    }

    /**
     * Output filter input
     *
     * @access     public
     * @return    string
     */
    public function filterInput()
    {
        // Attribtues
        $attributes = array(
            'class' => $this->form_slug.'-selectize skip',
            'placeholder' => $this->getParameter('placeholder', $this->field->field_name),
            );

        // String em up
        $attribute_string = '';

        foreach ($attributes as $attribute => $value) {
            $attribute_string .= $attribute.'="'.$value.'" ';
        }

        // Return an HTML dropdown
        return form_dropdown($this->getFilterSlug('is'), array(), null, $attribute_string);
    }

    /**
     * Process before saving
     * @return string
     */
    public function preSave()
    {
        // Delete existing
        ci()->pdb->table($this->getTableName())->where('entry_id', $this->entry->getKey())->delete();

        // Process / insert
        $insert = array();

        foreach ((array) ci()->input->post($this->form_slug) as $id) {

            // Gotta have an ID
            if ($id) {
                $insert[] = array(
                    'entry_id' => $this->entry->getKey(),
                    'related_id' => $id,
                    );
            }
        }

        // Insert new records
        if (! empty($insert)) {
            ci()->pdb->table($this->getTableName())->where('entry_id', $this->entry->getKey())->insert($insert);
        }

        // Return the count
        return count($insert);
    }

    /**
     * Pre Ouput
     *
     * Process before outputting on the CP. Since
     * there is less need for performance on the back end,
     * this is accomplished via just grabbing the title column
     * and the id and displaying a link (ie, no joins here).
     *
     * @return    mixed     null or string
     */
    public function stringOutput()
    {
        if($entries = $this->getEntriesOptions()) {
            return implode(', ', $entries);
        }

        return null;
    }

    /**
     * Pre Ouput Plugin
     * 
     * This takes the data from the join array
     * and formats it using the row parser.
     * 
     * @return array
     */
    public function pluginOutput()
    {
        if ($entries = $this->getRelationResult()) {
            return $entries->toArray();
        }

        return null;
    }

    /**
     * Pre Ouput Data
     * 
     * @return array
     */
    public function dataOutput()
    {
        if ($entries = $this->getRelationResult()) {
            return $entries;
        }

        return null;
    }

    /**
     * Run this when the field gets assigned
     * @return void
     */
    public function fieldAssignmentConstruct()
    {
        // Duplicate our instance
        $instance = $this;

        // Get the schema
        $schema = ci()->pdb->getSchemaBuilder();

        // Drop any existing
        $schema->dropIfExists($this->getTableName());

        /**
         * Create our pivot table
         */
        $schema->create($this->getTableName(), function($table) use ($instance) {
            $table->integer('entry_id');
            $table->integer('related_id');
        });
    }

    /**
     * Run this when the field gets unassigned
     * @return void
     */
    public function fieldAssignmentDestruct()
    {
        // Get the schema
        $schema = ci()->pdb->getSchemaBuilder();

        // Drop it like it's hot
        $schema->dropIfExists($this->getTableName());
    }

    /**
     * Do this when the namespace is destroyed
     * @return void
     */
    public function namespaceDestruct()
    {
        // Get the schema
        $schema = ci()->pdb->getSchemaBuilder();

        // Drop it like it's hot
        $schema->dropIfExists($this->getTableName());
    }

    /**
     * Ran when the entry is deleted
     * @return void
     */
    public function entryDestruct()
    {
        if ($id = $this->entry->getKey()) {
            
            // Delete by entry_id or related_id
            ci()->pdb
                ->table($this->getTableName())
                ->where('entry_id', $id)
                ->orWhere('related_id', $id)
                ->delete();
        }
    }

    /**
     * Choose a stream to relate to.. or remote source
     * @param  mixed $value
     * @return string
     */
    public function paramStream($value = '')
    {
        $options = StreamModel::getStreamAssociativeOptions();

        return form_dropdown('stream', $options, $value);
    }

    /**
     * Option format
     * @param  string $value
     * @return html
     */
    public function paramOptionFormat($value = '')
    {
        return form_input('option_format', $value);
    }

    /**
     * Label format
     * @param  string $value
     * @return html
     */
    public function paramLabelFormat($value = '')
    {
        return form_input('label_format', $value);
    }

    ///////////////////////////////////////////////////////////////////////////////
    // -------------------------       AJAX       ------------------------------ //
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Search for entries!
     * @return string JSON
     */
    public function ajaxSearch()
    {
        // Get the post data
        $post = ci()->input->post();


        /**
         * Get THIS field and type
         */
        $field = FieldModel::findBySlugAndNamespace($post['field_slug'], $post['stream_namespace']);
        $fieldType = $field->getType(null);

        
        /**
         * Get the relationClass
         */
        $relatedModel = $fieldType->getRelationClass();


        /**
         * Search for RELATED entries
         */
        if (method_exists($relatedModel, 'streamsMultipleAjaxSearch')) {
            echo $relatedModel::streamsMultipleAjaxSearch($fieldType);
        } else {
            echo $relatedModel::select(explode('|', $fieldType->getParameter('select_fields', '*')))
                ->where($fieldType->getParameter('search_columns', 'id'), 'LIKE', '%'.$post['term'].'%')
                ->take(10)
                ->get();
        }

        exit;
    }

    /**
     * Get the table
     * @return string
     */
    public function getTableName()
    {
        // Table name
        return $this->getStream()->stream_prefix.$this->getStream()->stream_slug.'_'.$this->field->field_slug;
    }

    /**
     * Get IDs for values
     * @return array 
     */
    public function getValueIds()
    {
        // Boom
        $entries = $this->getRelationResult();

        // Format
        return $entries ? $entries->getEntryOptions('id') : false;
    }

    /**
     * Options
     * @return array
     */
    public function getOptions()
    {
        return array();
    }

    /**
     * Get values for dropdown
     * @param  mixed $value string or bool
     * @return array
     */
    protected function getEntriesOptions($attribute = null)
    {
        // Boom
        $entries = $this->getRelationResult();

        // Format
        return $entries ? $entries->getEntryOptions($attribute) : false;
    }
}
