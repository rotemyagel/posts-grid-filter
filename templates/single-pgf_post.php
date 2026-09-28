<?php
/**
 * Single-post template for the pgf_post custom post type.
 *
 * Loaded via the single_template filter in class-pgf-single-template.php,
 * only when the active theme has no single-pgf_post.php of its own. Calls
 * get_header()/get_footer() like a normal theme template so it inherits
 * whatever header/nav/footer the active theme (or, on this install, its
 * Elementor Theme Builder templates) already renders.
 *
 * @package PostsGridFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="pgf-single" class="pgf-single site-main">
	<?php
	while ( have_posts() ) :
		the_post();

		$categories   = get_the_terms( get_the_ID(), PGF_Post_Type::TAX_CATEGORY );
		$tags         = get_the_terms( get_the_ID(), PGF_Post_Type::TAX_TAG );
		$demo_page_id = get_option( PGF_Seeder::DEMO_PAGE_OPTION );
		?>
		<article <?php post_class( 'pgf-single__article' ); ?>>
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="pgf-single__thumbnail">
					<?php
					the_post_thumbnail(
						'large',
						array(
							'loading'       => 'eager',
							'fetchpriority' => 'high',
							'alt'           => the_title_attribute( array( 'echo' => false ) ),
						)
					);
					?>
				</div>
			<?php endif; ?>

			<header class="pgf-single__header">
				<h1 class="pgf-single__title"><?php the_title(); ?></h1>

				<?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
					<div class="pgf-single__terms pgf-single__terms--categories">
						<?php foreach ( $categories as $term ) : ?>
							<?php $link = get_term_link( $term ); ?>
							<?php if ( ! is_wp_error( $link ) ) : ?>
								<a href="<?php echo esc_url( $link ); ?>" class="pgf-single__term"><?php echo esc_html( $term->name ); ?></a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</header>

			<div class="pgf-single__content">
				<?php the_content(); ?>
			</div>

			<?php if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) : ?>
				<div class="pgf-single__terms pgf-single__terms--tags">
					<?php foreach ( $tags as $term ) : ?>
						<?php $link = get_term_link( $term ); ?>
						<?php if ( ! is_wp_error( $link ) ) : ?>
							<a href="<?php echo esc_url( $link ); ?>" class="pgf-single__term pgf-single__term--tag">#<?php echo esc_html( $term->name ); ?></a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $demo_page_id && get_post_status( $demo_page_id ) ) : ?>
				<p class="pgf-single__back">
					<a href="<?php echo esc_url( get_permalink( $demo_page_id ) ); ?>">&larr; <?php esc_html_e( 'Back to the grid', 'rotem-posts-grid-filter' ); ?></a>
				</p>
			<?php endif; ?>
		</article>
		<?php
	endwhile;
	?>
</main>

<?php
get_footer();
