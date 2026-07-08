<?php
global $CFG;
require_once($CFG->dirroot. '/theme/edumy/ccn/block_handler/ccn_block_handler.php');
class block_cocoon_gallery_slider extends block_base
{
    // Declare first
    public function init()
    {
        $this->title = get_string('cocoon_gallery_slider', 'block_cocoon_gallery_slider');
    }

    // Declare second
    public function specialization()
    {
        global $CFG;
        include($CFG->dirroot . '/theme/edumy/ccn/block_handler/specialization.php');
    }

    public function get_content()
    {
        global $CFG, $DB;
        require_once($CFG->libdir . '/filelib.php');

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new \stdClass();

        // Title / subtitle — only show if genuinely non-empty.
        $title    = (!empty($this->config->title))    ? trim($this->config->title)    : '';
        $subtitle = (!empty($this->config->subtitle)) ? trim($this->config->subtitle) : '';

        // Columns config (kept for compatibility; not used in CSS flex slider).
        if (!empty($this->config->columns)) {
            $colmap = [7=>'8',6=>'7',5=>'6',4=>'5',3=>'4',2=>'3',1=>'2'];
            $columns = $colmap[$this->config->columns] ?? '1';
        } else {
            $columns = '3';
        }

        // ── Unique IDs for this instance ──────────────────────────────────────
        $iid      = $this->instance->id;
        $strip_id = 'ccn-gslider-strip-' . $iid;

        // ── CSS + JS ───────────────────────────────────────────────────────────
        echo '
<style>
/* ── gallery-slider wrap ── */
.ccn-gslider-wrap-' . $iid . ' {
    position: relative;
    padding: 0 40px;
}
.ccn-gslider-strip {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding-bottom: 6px;
}
.ccn-gslider-strip::-webkit-scrollbar { display: none; }

/* 3 columns desktop */
.ccn-gslider-item {
    scroll-snap-align: start;
    flex: 0 0 calc(33.33% - 8px);
    min-width: 0;
}
@media (max-width: 900px) { .ccn-gslider-item { flex: 0 0 calc(50% - 6px); } }
@media (max-width: 576px) {
    .ccn-gslider-wrap-' . $iid . ' { padding: 0 32px; }
    .ccn-gslider-item { flex: 0 0 78%; }
}

/* thumbnail — same height via aspect-ratio */
.ccn-gslider-thumb {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    aspect-ratio: 4 / 3;
    background: #111;
}
.ccn-gslider-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .3s ease;
}
.ccn-gslider-thumb:hover img { transform: scale(1.04); }

/* overlay */
.ccn-gslider-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,.45);
    opacity: 0;
    transition: opacity .25s;
}
.ccn-gslider-thumb:hover .ccn-gslider-overlay { opacity: 1; }
.ccn-gslider-overlay .flaticon-zoom-in {
    color: #fff;
    font-size: 32px;
}

/* nav arrows — same style as featured video block */
.ccn-gslider-wrap-' . $iid . ' .ccn-vs-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: none;
    background: rgba(255,255,255,.9);
    box-shadow: 0 2px 8px rgba(0,0,0,.25);
    font-size: 20px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    transition: background .2s, transform .2s;
    padding: 0;
}
.ccn-gslider-wrap-' . $iid . ' .ccn-vs-nav:hover {
    background: #fff;
    transform: translateY(-50%) scale(1.1);
}
.ccn-gslider-wrap-' . $iid . ' .ccn-vs-prev { left: 2px; }
.ccn-gslider-wrap-' . $iid . ' .ccn-vs-next { right: 2px; }
</style>

<script>
function ccnGSliderScroll_' . $iid . '(dir) {
    var strip = document.getElementById("' . $strip_id . '");
    var step  = strip.clientWidth * 0.85;
    strip.scrollBy({ left: dir * step, behavior: "smooth" });
}
</script>';

        // ── Build image items ─────────────────────────────────────────────────
        $fs         = get_file_storage();
        $files      = $fs->get_area_files($this->context->id, 'block_cocoon_gallery_slider', 'content');
        $items_html = '';

        foreach ($files as $file) {
            $filename = $file->get_filename();
            if ($filename === '.') {
                continue;
            }
            $url = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $filename
            );
            $items_html .= '
            <div class="ccn-gslider-item">
              <div class="ccn-gslider-thumb">
                <img src="' . $url . '" alt="' . s($filename) . '">
                <div class="ccn-gslider-overlay">
                  <a href="' . $url . '" target="_blank" rel="noopener">
                    <span class="flaticon-zoom-in"></span>
                  </a>
                </div>
              </div>
            </div>';
        }

        // ── Build output ──────────────────────────────────────────────────────
        $this->content->text = '
<section class="our-media pb30 pt20">
  <div class="container">';

        // Title + subtitle block — only render if title is non-empty.
        if ($title !== '') {
            $this->content->text .= '
    <div class="row mb20">
      <div class="col-lg-6 offset-lg-3">
        <div class="main-title text-center">
          <h3 class="mt0" style="color:#C9A227;font-size:40px;">'
                . format_text($title, FORMAT_HTML, ['filter' => true]) . '</h3>';

            if ($subtitle !== '') {
                $this->content->text .= '
          <p style="color:#C9A227;font-size:20px;font-weight:500;">'
                    . format_text($subtitle, FORMAT_HTML, ['filter' => true]) . '</p>';
            }

            $this->content->text .= '
        </div>
      </div>
    </div>';
        }

        // Slider — only render if there are images.
        if ($items_html !== '') {
            $this->content->text .= '
    <div class="ccn-gslider-wrap-' . $iid . '">
      <button class="ccn-vs-nav ccn-vs-prev"
              onclick="ccnGSliderScroll_' . $iid . '(-1)"
              aria-label="Previous">' . (right_to_left() ? '&#8250;' : '&#8249;') . '</button>

      <div class="ccn-gslider-strip" id="' . $strip_id . '">
        ' . $items_html . '
      </div>

      <button class="ccn-vs-nav ccn-vs-next"
              onclick="ccnGSliderScroll_' . $iid . '(1)"
              aria-label="Next">' . (right_to_left() ? '&#8249;' : '&#8250;') . '</button>
    </div>';
        }

        $this->content->text .= '
  </div>
</section>';

        return $this->content;
    }

    /**
     * Allow multiple instances in a single course?
     */
    public function instance_allow_multiple()
    {
        return true;
    }

    /**
     * Enables global configuration of the block in settings.php.
     */
    function has_config()
    {
        return true;
    }

    /**
     * Sets the applicable formats for the block.
     */
    function applicable_formats()
    {
        $ccnBlockHandler = new ccnBlockHandler();
        return $ccnBlockHandler->ccnGetBlockApplicability(array('all'));
    }

    public function html_attributes()
    {
        global $CFG;
        $attributes = parent::html_attributes();
        include($CFG->dirroot . '/theme/edumy/ccn/block_handler/attributes.php');
        return $attributes;
    }
}
