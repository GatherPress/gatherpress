<?php
/**
 * Base class for GatherPress settings.
 *
 * This class is part of the core functionality in GatherPress and serves as a foundation
 * for creating and managing settings pages. It provides essential methods and properties
 * to streamline the process of adding custom settings to the GatherPress platform.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Settings;

/**
 * Class Base.
 *
 * This class provides a foundation for creating settings pages in GatherPress.
 *
 * @since 1.0.0
 */
class Base {

	/**
	 * The name of the settings page.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $name = '';

	/**
	 * The priority of the settings page within the sub-pages list.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	protected int $priority = 10;

	/**
	 * An array of sections to be displayed on the settings page.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected array $sections = array();

	/**
	 * The slug used to identify the settings page.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $slug = '';

	/**
	 * Constructor method for initializing the class and setting up hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_filter( 'gatherpress_sub_pages', array( $this, 'set_sub_page' ) );
	}

	/**
	 * Callback function to set the sub-page for GatherPress.
	 *
	 * This method serves as a callback function to set the sub-page for the GatherPress plugin.
	 * It takes an array of existing sub-pages and adds the sub-page defined by the current instance.
	 *
	 * @since 1.0.0
	 *
	 * @param array $sub_pages An array of sub-pages for GatherPress.
	 * @return array Modified array with the sub-page added.
	 */
	public function set_sub_page( array $sub_pages ): array {
		$sub_pages[ $this->slug ] = $this->page();

		return $sub_pages;
	}

	/**
	 * Get the value of a property.
	 *
	 * This method allows you to retrieve the value of a specific property by providing its name.
	 * If the property exists, its value is returned; otherwise, it returns null.
	 *
	 * @since 1.0.0
	 *
	 * @param string $property The name of the property to retrieve.
	 * @return mixed|null The value of the property or null if it doesn't exist.
	 */
	public function get( string $property ) {
		return $this->$property ?? null;
	}

	/**
	 * Get an array representation of the settings page.
	 *
	 * This method returns an array that represents the settings page, including its name, priority, and sections.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array representing the settings page.
	 */
	public function page(): array {
		return array(
			'name'     => $this->name,
			'priority' => $this->priority,
			'sections' => $this->sections,
		);
	}

}
