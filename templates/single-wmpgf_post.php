<?php
/**
 * Single-post template for wmpgf_post, used only when the theme has none.
 * Uses get_header()/get_footer() so the theme's own chrome still renders.
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="wmpgf-single" class="wmpgf-single site-main">
	<?php
	while ( have_posts() ) :
		the_post();

		$categories   = get_the_terms( get_the_ID(), WMPGF_Post_Type::TAX_CATEGORY );
		$tags         = get_the_terms( get_the_ID(), WMPGF_Post_Type::TAX_TAG );
		$demo_page_id = get_option( WMPGF_Seeder::DEMO_PAGE_OPTION );
		?>
		<article <?php post_class( 'wmpgf-single__article' ); ?>>
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="wmpgf-single__thumbnail">
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

			<header class="wmpgf-single__header">
				<h1 class="wmpgf-single__title"><?php the_title(); ?></h1>

				<?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
					<div class="wmpgf-single__terms wmpgf-single__terms--categories">
						<?php foreach ( $categories as $category_term ) : ?>
							<?php $category_link = get_term_link( $category_term ); ?>
							<?php if ( ! is_wp_error( $category_link ) ) : ?>
								<a href="<?php echo esc_url( $category_link ); ?>" class="wmpgf-single__term"><?php echo esc_html( $category_term->name ); ?></a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</header>

			<div class="wmpgf-single__content">
				<?php the_content(); ?>
			</div>

			<?php if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) : ?>
				<div class="wmpgf-single__terms wmpgf-single__terms--tags">
					<?php foreach ( $tags as $tag_term ) : ?>
						<?php $tag_link = get_term_link( $tag_term ); ?>
						<?php if ( ! is_wp_error( $tag_link ) ) : ?>
							<a href="<?php echo esc_url( $tag_link ); ?>" class="wmpgf-single__term wmpgf-single__term--tag">#<?php echo esc_html( $tag_term->name ); ?></a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $demo_page_id && get_post_status( $demo_page_id ) ) : ?>
				<p class="wmpgf-single__back">
					<a href="<?php echo esc_url( get_permalink( $demo_page_id ) ); ?>">&larr; <?php esc_html_e( 'Back to the grid', 'wm-posts-grid-filter' ); ?></a>
				</p>
			<?php endif; ?>
		</article>
		<?php
	endwhile;
	?>
</main>

<?php
get_footer();
