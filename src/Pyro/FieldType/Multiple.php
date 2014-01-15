<?php namespace Pyro\FieldType;

use Pyro\Model\Eloquent;
use Pyro\Module\Streams_core\AbstractFieldType;
use Pyro\Module\Streams_core\FieldModel;
use Pyro\Module\Streams_core\StreamModel;

/**
 * PyroStreams Multiple Field Type
 *
 * @package        PyroCMS\Core\Modules\Streams Core\Field Types
 * @author        Parse19
 * @copyright    Copyright (c) 2011 - 2012, Parse19
 * @license        http://parse19.com/pyrostreams/docs/license
 * @link        http://parse19.com/pyrostreams
 */
class Multiple extends AbstractFieldType
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
     * Custom parameters
     * @var array
     */
    public $custom_parameters = array(
        'stream',
        'max_selections',
        'search_fields',
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
     * Yes please
     * @var boolean
     */
    public $alt_process = true;

    /**
     * Our pivot table
     */
    public $table = null;

    ///////////////////////////////////////////////////////////////////////////////
    // -------------------------    METHODS       ------------------------------ //
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Fired when form is built per field
     * @param  boolean $field 
     */
    public function fieldEvent()
    {return false;
        // Get related entries
        $entries = $this->getRelationResult();

        // Basically the selectize config mkay?
        $this->appendMetadata(
            $this->view(
                'data/multiple.js.php',
                array(
                    'field_type' => $this,
                    'entries' => $entries,
                    ),
                true
                )
            );
    }

    /**
     * Relation
     * @return object The relation object
     */
    public function relation()
    {
        return $this->belongsToMany($this->getRelationClass(), $this->getTable(), 'entry_id', 'related_id');
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
        // Is this a small enough dataset?
        return form_dropdown($this->form_slug, $this->getOptions(), $this->getValueIds());
    }

    /**
     * Output filter input
     *
     * @access     public
     * @return    string
     */
    public function filterInput()
    {
        // Set the value from the thingie
        $this->setValue(ci()->input->get($this->getFilterSlug('is')));

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
        return form_dropdown($this->getFilterSlug('is'), array(), null, $attribute_string);
    }

    /**
     * Process before saving
     * @return string
     */
    public function preSave()
    {
        
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
    {return 'Poop';
        if($entries = $this->getEntriesTitles()) {
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
        if ($entries = $this->getRelationResult())
        {
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
        if ($entries = $this->getRelationResult())
        {
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
        // Setup
        $this->setTable();

        // Duplicate our instance
        $instance = $this;

        // Get the schema
        $schema = ci()->pdb->getSchemaBuilder();

        // Drop any existing
        $schema->dropIfExists($this->table);

        /**
         * Create our pivot table
         */
        $schema->create($this->table, function($table) use ($instance) {
            $table->integer('entry_id');
            $table->integer('related_id');

            $table->index('entry_id');
            $table->index('related_id');
        });
    }

    /**
     * Run this when the field gets unassigned
     * @return void
     */
    public function fieldAssignmentDestruct()
    {
        // Get our table name
        $this->setTable();

        // Get the schema
        $schema = ci()->pdb->getSchemaBuilder();

        // Drop it like it's hot
        $schema->dropIfExists($this->table);
    }

    /**
     * Do this when the namespace is destroyed
     * @return void
     */
    public function namespaceDestruct()
    {
        // Get our table name
        $this->setTable();

        // Get the schema
        $schema = ci()->pdb->getSchemaBuilder();

        // Drop it like it's hot
        $schema->dropIfExists($this->table);
    }

    /**
     * Ran when the entry is deleted
     * @return void
     */
    public function entryDestruct()
    {
        if (isset($this->entry->id)) {
            // Setup
            $this->setTable();

            // Delete by entry_id or related_id
            ci()->pdb
                ->table($this->table)
                ->where('entry_id', $this->entry->id)
                ->orWhere('related_id', $this->entry->id)
                ->delete();
        }
    }

    ///////////////////////////////////////////////////////////////////////////////
    // -------------------------    PARAMETERS    ------------------------------ //
    ///////////////////////////////////////////////////////////////////////////////

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
                ->where($fieldType->getParameter('search_fields', 'id'), 'LIKE', '%'.$post['term'].'%')
                ->take(10)
                ->get();
        }

        exit;
    }

    ///////////////////////////////////////////////////////////////////////////////
    // -------------------------     UTILITIES    ------------------------------ //
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Get the table
     * @return string
     */
    public function getTable()
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
        // List related entry IDs
        return $this->getRelationResult()->lists('id');
    }

    /**
     * Options
     * @return array
     */
    public function getOptions()
    {
        // Get options
        $options = array();

        if ($relation_class = $this->getRelationClass()) {

            $instance = new $relation_class;

            if ($instance instanceof EntryModel) {
            
                list($stream_slug, $stream_namespace) = explode('.', $this->getParameter('stream'));

                $stream = StreamModel::findBySlugAndNamespace($stream_slug, $stream_namespace);

                $options = $relation_class::stream($stream_slug, $stream_namespace)->limit(1000)->select('*')->get()->toArray();
                
                $option_format = $this->getParameter('option_format', '{{ '.($stream->title_column ? $stream->title_column : 'id').' }}'); 

            } else {

                $options = $relation_class::limit(1000)->select('*')->get()->toArray();

                $option_format = $this->getParameter('option_format', '{{ '.$this->getParameter('title_field', 'id').' }}'); 

            }
        }

        // Format options
        $formatted_options = array();

        foreach ($options as $option) {
                $formatted_options[$option[$this->getParameter('value_field', 'id')]] = ci()->parser->parse_string($option_format, $option, true, false, array(), false);
        }

        // Boom
        return $formatted_options;
    }

    /**
     * Get values for dropdown
     * @param  mixed $value string or bool
     * @return array
     */
    protected function getEntriesTitles($value = false)
    {
        // Boom
        $entries = $this->getRelationResult();

        // Format
        return $entries ? $entries->getEntryOptions() : false;
    }
}
