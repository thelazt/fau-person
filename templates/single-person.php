<?php
get_header(); ?>
    <?php while ( have_posts() ) : the_post(); ?>
        <div id="content">
            <div class="container">
                <div class="row">
                    <div class="span8">
                    <?php 
                        echo FAU_Person_Shortcodes::fau_person_page(get_the_ID());
                    ?>         
                    </div>
                </div>
            </div>
        </div>
    <?php endwhile;
get_footer();
