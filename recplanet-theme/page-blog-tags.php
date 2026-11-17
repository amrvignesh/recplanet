<?php
/**
 * Template Name: Blog Tags
 * Every tag used on the blog and on photos, A to Z under a letter bar, as the old site's Blog Tags page.
 */
get_header();
$tags   = get_terms( [ 'taxonomy' => 'post_tag', 'hide_empty' => true, 'number' => 0 ] );
$tags   = is_array( $tags ) ? $tags : [];
$groups = [];
foreach ( $tags as $t ) {
	$l = strtoupper( mb_substr( remove_accents( $t->name ), 0, 1 ) );
	$groups[ preg_match( '/^[A-Z]$/', $l ) ? $l : '#' ][] = $t;
}
foreach ( $groups as &$g ) {
	usort( $g, fn( $a, $b ) => strcasecmp( remove_accents( $a->name ), remove_accents( $b->name ) ) );
}
unset( $g );
$letters = array_merge( range( 'A', 'Z' ), [ '#' ] );
$anchor  = fn( string $l ) => 'tag-' . ( '#' === $l ? 'other' : $l );
?>
<div class="wrap">
  <div class="phead one">
    <div>
      <div class="eyebrow">Blog Tags</div>
      <h1>Every subject the blog has touched</h1>
      <p class="lead" style="margin-top:14px"><?php echo esc_html( number_format( count( $tags ) ) ); ?> tags across the posts and the contest photos, A to Z. The number is how many posts carry the tag.</p>
    </div>
  </div>
  <?php if ( $tags ) : ?>
  <nav class="chips azbar" aria-label="Tags by first letter">
    <?php foreach ( $letters as $l ) : ?><?php if ( isset( $groups[ $l ] ) ) : ?><a class="chip" href="#<?php echo esc_attr( $anchor( $l ) ); ?>"><?php echo esc_html( $l ); ?></a><?php else : ?><span class="chip off" aria-disabled="true"><?php echo esc_html( $l ); ?></span><?php endif; ?><?php endforeach; ?>
  </nav>
  <?php foreach ( $letters as $l ) : if ( ! isset( $groups[ $l ] ) ) { continue; } ?>
  <section class="tag-group" id="<?php echo esc_attr( $anchor( $l ) ); ?>">
    <h2><?php echo esc_html( '#' === $l ? 'Numbers and symbols' : $l ); ?></h2>
    <div class="chips">
      <?php foreach ( $groups[ $l ] as $t ) : ?><a class="chip" href="<?php echo esc_url( get_tag_link( $t ) ); ?>"><?php echo esc_html( $t->name ); ?> <span class="n"><?php echo esc_html( number_format( $t->count ) ); ?></span></a><?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>
  <?php else : ?><p class="ph">No tags yet. They arrive with the blog import.</p><?php endif; ?>
  <div style="height:48px"></div>
</div>
<?php get_footer(); ?>
