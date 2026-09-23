<?php
/**
 * Home: the counter, near me, the ledger, the ribbon, coverage, the contest, the journal.
 */
get_header();
$c      = RP\Counter::payload();
$us     = $c['us'];
$world  = $c['world'];
$aq     = $c['antarctica'];
$rest   = $c['rest'];
$cities = wp_count_terms( [ 'taxonomy' => 'rp_place', 'hide_empty' => true, 'meta_key' => 'rp_level', 'meta_value' => 'city' ] );
$countries = wp_count_terms( [ 'taxonomy' => 'rp_place', 'hide_empty' => true, 'meta_key' => 'rp_level', 'meta_value' => 'country' ] );
$recent = rp_index_rows( 'lat IS NOT NULL', [], 'post_id DESC', 3 );
$states = get_terms( [ 'taxonomy' => 'rp_place', 'hide_empty' => true, 'meta_key' => 'rp_level', 'meta_value' => 'state', 'number' => 12, 'orderby' => 'count', 'order' => 'DESC' ] );
$photos = get_posts( [ 'post_type' => 'rp_photo', 'posts_per_page' => 4, 'post_status' => 'publish', 'meta_key' => 'rp_votes', 'orderby' => 'meta_value_num', 'order' => 'DESC' ] );
$posts  = get_posts( [ 'posts_per_page' => 3 ] );
?>
<?php get_template_part( 'template-parts/hero', null, [ 'headline' => $c['headline'], 'world' => $world, 'us' => $us, 'aq' => $aq, 'rest' => $rest ] ); ?>

<div class="wrap">
  <div class="hero-stats">
    <div><span class="num"><?php echo esc_html( number_format( $world['count'] ) ); ?></span><span>places mapped by hand</span></div>
    <div><span class="num"><?php echo esc_html( number_format( (int) $cities ) ); ?></span><span>cities on file</span></div>
    <div><span class="num"><?php echo esc_html( number_format( (int) $countries ) ); ?></span><span>countries</span></div>
    <div><span class="num">2008</span><span>counting since</span></div>
  </div>
</div>

<section id="near">
  <div class="wrap">
    <div class="section-head">
      <div><div class="eyebrow">Fifteen minutes from you</div><h2>What can I do, right now, near here?</h2></div>
      <p id="nearNote">Pick an activity and we'll find the closest places to you. Until you allow location, here are the newest additions.</p>
    </div>
    <div class="chips" id="nearChips" role="group" aria-label="Activity" style="margin-bottom:24px">
      <button class="chip on" type="button" data-a="">Anything</button>
      <?php foreach ( [ 'Playground', 'Picnicking', 'Basketball', 'Hiking', 'Fishing', 'Swimming', 'Dog-Park' ] as $a ) : ?>
        <button class="chip" type="button" data-a="<?php echo esc_attr( $a ); ?>"><?php echo esc_html( $a ); ?></button>
      <?php endforeach; ?>
      <button class="chip btn-locate" type="button" id="locate">◎ Use my location</button>
    </div>
    <div class="cards" id="nearCards">
      <?php foreach ( $recent as $r ) { echo rp_park_card( $r, 'Just added' ); } ?>
    </div>
  </div>
</section>

<?php get_template_part( 'template-parts/ledger', null, [ 'ledger' => $c['ledger'] ] ); ?>

<div class="band">
  <div class="photo" data-depth="-0.18" style="background-image:url('<?php echo esc_url( RP_THEME_URI . '/assets/img/leaves.jpg' ); ?>')"></div>
  <div class="tint"></div>
  <div class="wrap">
    <div class="eyebrow" style="color:#DFF3B8">Hand made since 2008</div>
    <blockquote>“My database is hand made. Not a single site scraper was used, and all places have been checked for amenities.”</blockquote>
    <cite>Taylor Marshall, founder, Atlanta, Georgia</cite>
  </div>
</div>

<section id="ribbon">
  <div class="wrap">
    <div class="section-head">
      <div><div class="eyebrow">The acre ribbon</div><h2>Every place is a share of the whole</h2></div>
      <p>This strip sits on every page: the breadcrumb and the counter at once, so a one-acre playground still reads as part of the whole.</p>
    </div>
    <?php
    $rows = [ [ 'United States · ' . number_format( $us['count'] ) . ' places', $us['acres'], home_url( '/world/us' ), false ] ];
    foreach ( array_slice( $states, 0, 3 ) as $st ) {
      $s = rp_place_stats( $st );
      $rows[] = [ $st->name . ' · ' . number_format( $s['count'] ) . ' places', $s['acres'], get_term_link( $st ), false ];
    }
    echo rp_acre_ribbon( $rows );
    ?>
    <p style="font-size:13px;color:var(--muted);margin-top:12px">Bars are on a log scale so small places stay visible. Every figure is summed from the records.</p>
  </div>
</section>

<section class="coverage" id="coverage">
  <div class="wrap grid">
    <div>
      <div class="eyebrow" style="color:var(--lime)">Where we are</div>
      <h2>Fifty states, walked end to end, one park at a time</h2>
      <p style="margin-top:14px">The states with the most places on file. Every record was checked by a person, and where the survey is thin we say so.</p>
      <a class="btn btn-lime" href="<?php echo esc_url( home_url( '/states' ) ); ?>" style="margin-top:24px">Every state and city</a>
    </div>
    <div class="mapbox">
      <div class="bars light">
        <?php foreach ( $states as $st ) { $s = rp_place_stats( $st ); $top = $top ?? max( 1, $s['count'] ); ?>
          <a href="<?php echo esc_url( get_term_link( $st ) ); ?>"><span><?php echo esc_html( $st->name ); ?></span><span class="t"><span class="f" style="width:<?php echo (int) round( $s['count'] / $top * 100 ); ?>%"></span></span><b><?php echo esc_html( number_format( $s['count'] ) ); ?></b></a>
        <?php } ?>
      </div>
    </div>
  </div>
</section>

<?php if ( $photos ) : ?>
<section id="contest-home">
  <div class="wrap">
    <div class="section-head">
      <div><div class="eyebrow">Photo contest</div><h2>The places, through members’ eyes</h2></div>
      <div style="display:flex;flex-direction:column;gap:10px;align-items:flex-start"><span class="prize">Rate photos, win prizes</span><p>Anyone can rate. Every photo is pinned to the place it was taken.</p></div>
    </div>
    <div class="gallery">
      <?php foreach ( $photos as $i => $p ) : $r = (float) get_post_meta( $p->ID, 'rp_score', true ); ?>
        <a class="tile" href="<?php echo esc_url( get_permalink( $p ) ); ?>" style="--r:<?php echo esc_attr( [ '-1.5deg', '1.2deg', '-0.8deg', '1.6deg' ][ $i % 4 ] ); ?>">
          <?php echo has_post_thumbnail( $p ) ? get_the_post_thumbnail( $p, 'rp-tile', [ 'loading' => 'lazy' ] ) : rp_scene_svg( $p->ID ); ?>
          <span class="votes">★ <?php echo esc_html( number_format( $r, 1 ) ); ?></span>
          <div class="cap"><?php echo esc_html( get_the_title( $p ) ); ?><small><?php echo esc_html( get_post_meta( $p->ID, 'rp_contributor', true ) ?: get_the_author_meta( 'display_name', $p->post_author ) ); ?></small></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ( $posts ) : ?>
<section id="journal" style="background:var(--bg-2)">
  <div class="wrap">
    <div class="section-head"><div><div class="eyebrow">Blog</div><h2>From the trail notebook</h2></div><a class="btn btn-ghost" href="<?php echo esc_url( home_url( '/blog' ) ); ?>">All posts</a></div>
    <div class="posts">
      <?php foreach ( $posts as $p ) : ?>
        <a class="post" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><time><?php echo esc_html( get_the_date( 'j F Y', $p ) ); ?></time><h3><?php echo esc_html( get_the_title( $p ) ); ?></h3><p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $p->post_content ), 22 ) ); ?></p></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="contrib" id="contribute">
  <div class="wrap">
    <div><h2>Your photos belong on the map</h2><p style="margin-top:10px">Join free to upload photos, pin them to the parks you know, and enter the contest. Membership is free and always will be.</p></div>
    <div style="display:flex;gap:12px;flex-wrap:wrap"><a class="btn btn-orange" href="<?php echo esc_url( wp_registration_url() ); ?>">Join free</a><a class="btn btn-white" href="<?php echo esc_url( home_url( '/contest' ) ); ?>">Enter the contest</a></div>
  </div>
</section>
<?php get_footer(); ?>
