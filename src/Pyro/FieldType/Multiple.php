<?php namespace Pyro\FieldType;

use Pyro\Model\Eloquent;
use Pyro\Module\Streams_core\AbstractFieldType;
use Pyro\Module\Streams_core\EntryModel;
use Pyro\Module\Streams_core\FieldModel;
use Pyro\Module\Streams_core\StreamModel;

/**
 * PyroStreams Multiple Field Type
 *
 * @package		PyroCMS\Core\Modules\Streams Core\Field Types
 * @author		Parse19
 * @copyright	Copyright (c) 2011 - 2012, Parse19
 * @license		http://parse19.com/pyrostreams/docs/license
 * @link		http://parse19.com/pyrostreams
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
		'label_field',
		'search_fields',
		'placeholder',
		'option_format',
		'label_format',
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
	 * Our pivot table
	 */
	public $table = null;

	///////////////////////////////////////////////////////////////////////////////
	// -------------------------	METHODS 	  ------------------------------ //
	///////////////////////////////////////////////////////////////////////////////

	/**
	 * Fired when form is built per field
	 * @param  boolean $field 
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
		// Extract our relationship stream
		list($stream_slug, $stream_namespace) = explode('.', $this->getParameter('stream'));

		// Get the relationship class
		if (! $relation_class = $this->getRelationClass()) return null;

		// If the stream doesn't exist..
		if (! $stream = StreamModel::findBySlugAndNamespace($stream_slug, $stream_namespace, true)) return null;

		// Create a new instance
		// of our relation class to use/abuse
		$instance = new $relation_class;

		// If it's an entry model - boomskie
		if ($instance instanceof EntryModel) {
			return $this->belongsToManyEntries($relation_class)->select('*');
		}

		// Otherwise - boomskie too
		return $this->belongsToMany($relation_class);
	}

	/**
	 * Output form input
	 *
	 * @access 	public
	 * @return	string
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

		foreach ($attributes as $attribute => $value)
			$attribute_string .= $attribute.'="'.$value.'" ';

		// Return an HTML dropdown
		return form_dropdown($this->form_slug, array(), null, $attribute_string);
	}

	/**
	 * Output filter input
	 *
	 * @access 	public
	 * @return	string
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

		foreach ($attributes as $attribute => $value)
			$attribute_string .= $attribute.'="'.$value.'" ';

		// Return an HTML dropdown
		return form_dropdown($this->getFilterSlug('is'), array(), null, $attribute_string);
	}

	/**
	 * Process before saving
	 * @return string
	 */
	public function preSave()
	{
		// Set our table
		$this->setTable();

		// Delete existing
		ci()->pdb->table($this->table)->where('entry_id', $this->entry->getKey())->delete();

		// Process / insert
		$insert = array();

		foreach ((array) ci()->input->post($this->form_slug) as $id) {

			// Gotta have an ID
			if (empty($id) or $id < 1) continue;

			// Add to our insert
			$insert[] = array(
				'entry_id' => $this->entry->getKey(),
				'related_id' => $id
				);
		}

		// Insert new records
		if (! empty($insert)) 
		{
			ci()->pdb->table($this->table)->where('entry_id', $this->entry->getKey())->insert($insert);
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
	 * @return	mixed 	null or string
	 */
	public function stringOutput()
	{
		if($entries = $this->getEntriesTitles() and ! empty($entries))
		{
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
		if ($entries = $this->getRelationResult() and ! empty($entries))
		{
			return $entries->asPlugin();
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
		if ($entries = $this->getRelationResult() and ! empty($entries))
		{
			return $entries;
		}

		return null;
	}

	///////////////////////////////////////////////////////////////////////////////
	// -------------------------	PARAMETERS 	  ------------------------------ //
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
	// -------------------------	   AJAX 	  ------------------------------ //
	///////////////////////////////////////////////////////////////////////////////

	/**
	 * Search for entries!
	 * @return string JSON
	 */
	public function ajaxSearch()
	{
		// Get the search term first
		$term = ci()->input->post('term');


		/**
		 * List THIS stream, namespace and field_slug
		 */
		list($stream_namespace, $stream_slug, $field_slug) = explode('-', ci()->uri->segment(6));
		

		/**
		 * Get THIS field and type
		 */
        $field = FieldModel::findBySlugAndNamespace($field_slug, $stream_namespace);
		$field_type = $field->getType(null);
		

		/**
		 * Populate RELATED stream variables
		 */
		list($related_stream_slug, $related_stream_namespace) = explode('.', $field_type->getParameter('stream'));


		/**
		 * Search for RELATED entries
		 */
		echo $entries = EntryModel::stream($related_stream_slug, $related_stream_namespace)
			->select('*')
			->where($field_type->getParameter('search_fields', 'id'), 'LIKE', '%'.$term.'%')
			->take(10)
			->get();

		exit;
	}

	///////////////////////////////////////////////////////////////////////////////
	// -------------------------	UTILITIES 	  ------------------------------ //
	///////////////////////////////////////////////////////////////////////////////

	/**
	 * Return the table needed for pivot
	 * @return string
	 */
	public function setTable()
	{
		$this->table = $this->stream->stream_prefix.$this->stream->stream_slug.'_'.$this->field->field_slug;
	}

	/**
	 * Relation class
	 * @return string
	 */
	public function getRelationClass()
	{
		return $this->getParameter('relation_class', 'Pyro\Module\Streams_core\EntryModel');
	}

	/**
	 * Count total possible options
	 * @return [type] [description]
	 */
	public function totalOptions()
	{
		// Return that shiz
		return EntryModel::stream($this->getParameter('stream'))->select('id')->count();
	}

	/**
	 * Get values for dropdown
	 * @param  mixed $value string or bool
	 * @return array
	 */
	protected function getEntriesTitles($value = false)
	{
		// Break apart the stream
		$stream = explode('.', $this->getParameter('stream'));
		$stream = StreamModel::findBySlugAndNamespace($stream[0], $stream[1]);

		// Boom
		$entries = $this->getRelationResult();

		// Format
		return $entries ? $entries->getEntryOptions($stream->title_column) : array();
	}
}
