<?php
/**
 * @package ZEIT ONLINE Blog Options
 *
 * Plugin Name:       ZEIT ONLINE Authors Widget
 * Plugin URI:        https://github.com/ZeitOnline/zon-authors-widget
 * Description:       Wordpress widget to display a context sensitive list of authors
 * Version:           1.0.0
 * Author:            Moritz Stoltenburg
 * Author URI:        http://slomo.de/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * GitHub Plugin URI: https://github.com/ZeitOnline/zon-authors-widget
*/

/**
 * Adds ZonBlogAuthorsWidget widget.
 */
class ZonBlogAuthorsWidget extends WP_Widget {
	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			'zon-authors-widget', // Base ID
			__('ZON Blog Autoren', 'zeitonline'), // Name
			array( 'description' => __( 'Liste der aktiven Autoren', 'zeitonline' ), ) // Args
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', $instance['title'] );

		echo $args['before_widget'];

		if ( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		if ( is_front_page() ) {
			$instance['limit'] = 5;
		} else {
			$instance['limit'] = 3;
		}

		// exclude current author on request
		$instance['exclude'] = ( $instance['show_author'] == 'no' ) ? get_the_author_meta( 'ID' ) : null;

		// get author IDs
		$authors = $this->_get_authors($instance);

		if ( count($authors) ) {
			echo '<ul class="widget-authors">' . "\n";

			foreach ( $authors as $id ) {
				if ( $instance['show_author'] == 'only' ) {
					$avatar   = get_avatar( $id, 80 );
					$user_url = get_the_author_meta( 'user_url', $id );

					if ( ! empty( $user_url ) ) {
						$user_url = sprintf( '<a href="%1$s" class="user-url">%1$s</a>', esc_url($user_url) );
					}
				}
				else {
					$avatar   = get_avatar( $id, 60 );
					$user_url = null;
				}

				$display_name = get_the_author_meta( 'display_name', $id );
				$description  = get_the_author_meta( 'description', $id );
				$posts_url    = get_author_posts_url( $id );

				echo <<<HTML
				<li>
					<a href="$posts_url">
						$avatar
						<h3 class="widget-item">$display_name</h3>
						<p>$description</p>
					</a>
					$user_url
				</li>

HTML;
			}

			echo "</ul>\n";
		}

		if ( $instance['page_id'] ) {
			$href = get_page_link($instance['page_id']);
			$link_label = ( !empty($instance['link_label']) ) ? esc_html($instance['link_label']) : 'Alle Blogger';

			echo <<<HTML
				<div class="widget-footer">
					<a href="$href">$link_label</a>
				</div>

HTML;
		}

		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		wp_register_style( $this->id_base, plugins_url($this->id_base . '.css', __FILE__ ) );
		wp_enqueue_style( $this->id_base );

		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		else {
			$title = 'Einige Autoren dieses Blogs';
		}

		if ( isset( $instance[ 'link_label' ] ) ) {
			$link_label = $instance[ 'link_label' ];
		}
		else {
			$link_label = 'Alle Blogger';
		}

		if ( isset( $instance[ 'show_author' ] ) ) {
			$show_author = $instance[ 'show_author' ];
		}
		else {
			$show_author = 'yes';
		}

		$options = array(
			'selected'         => ( isset( $instance[ 'page_id' ] ) ) ? $instance[ 'page_id' ] : 0,
			'name'             => $this->get_field_name( 'page_id' ),
			'id'               => $this->get_field_id( 'page_id' ),
			'show_option_none' => '--',
		);

		$show_author_field_name = $this->get_field_name( 'show_author' );

		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label><input type="radio" name="<?php echo $show_author_field_name; ?>" value="yes"  <?php checked( $show_author, 'yes' );  ?>> Zeige alle Autoren</label><br>
			<label><input type="radio" name="<?php echo $show_author_field_name; ?>" value="only" <?php checked( $show_author, 'only' ); ?>> Zeige nur aktuellen Autor</label><br>
			<label><input type="radio" name="<?php echo $show_author_field_name; ?>" value="no"   <?php checked( $show_author, 'no' );   ?>> Verberge aktuellen Autor</label><br>
		</p>
		<p>
			<fieldset class="zon-fieldset">
				<legend>Link</legend>
				<label for="<?php echo $options['id']; ?>">Seite:</label>
				<?php wp_dropdown_pages($options); ?><br>
				<label for="<?php echo $this->get_field_id( 'link_label' ); ?>"><?php _e( 'Label:' ); ?></label>
				<input id="<?php echo $this->get_field_id( 'link_label' ); ?>" name="<?php echo $this->get_field_name( 'link_label' ); ?>" type="text" value="<?php echo esc_attr( $link_label ); ?>">
			</fieldset>
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array(
			'title' => '',
			'link_label' => '',
			'page_id' => '',
			'show_author' => '',
		);

		foreach ($instance as $key => $value) {
			if ( ! empty( $new_instance[$key] ) ) {
				$instance[$key] = strip_tags( $new_instance[$key] );
			}
		}

		return $instance;
	}

	/**
	 * Get user IDs of blog authors
	 *
	 * @param array $instance Current widget instance
	 *
	 * @return array user IDs
	 */
	private function _get_authors( $instance ) {
		if ( $instance['show_author'] == 'only' ) {
			return array( get_the_author_meta( 'ID' ) );
		}

		$users = array();

		$authors = get_users( array(
			'fields'  => 'ID',
			// 'role'    => 'author',
			'who'     => 'authors',
			'exclude' => array( $instance['exclude'] ),
		) );

		foreach ( $authors as $id ) {
			$post_count = count_user_posts( $id );

			// Move on if user has not published a post (yet).
			if ( !$post_count ) {
				continue;
			}

			$users[] = $id;
		}

		shuffle($users);

		if ( !empty($instance['limit']) && $instance['limit'] < count($users) ) {
			$users = array_slice($users, 0, $instance['limit']);
		}

		return $users;
	}
}

// register ZonBlogAuthorsWidget widget
function register_zon_blog_authors_widget() {
	register_widget( 'ZonBlogAuthorsWidget' );
}
add_action( 'widgets_init', 'register_zon_blog_authors_widget' );
