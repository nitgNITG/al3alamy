<?php
global $CFG;
require_once($CFG->dirroot . '/theme/edumy/ccn/block_handler/ccn_block_handler.php');

class block_cocoon_gallery extends block_base
{
    public function init()
    {
        $this->title = get_string('cocoon_gallery', 'block_cocoon_gallery');
    }

    public function specialization()
    {
        global $CFG, $DB;
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

        // Only set title/subtitle if they are genuinely non-empty.
        $title    = (!empty($this->config->title))    ? trim($this->config->title)    : '';
        $subtitle = (!empty($this->config->subtitle)) ? trim($this->config->subtitle) : '';

        // ── Unique IDs for this instance ──────────────────────────────────────
        $iid      = $this->instance->id;
        $strip_id = 'ccn-gallery-strip-' . $iid;

        // ── CSS + JS (same arrow style as featured video block) ───────────────
        echo '
<style>
/* ── gallery slider wrap ── */
.ccn-gallery-wrap-' . $iid . ' {
    position: relative;
    padding: 0 40px;
}
.ccn-gallery-strip {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding-bottom: 6px;
}
.ccn-gallery-strip::-webkit-scrollbar { display: none; }

/* 3 columns desktop */
.ccn-gallery-item {
    scroll-snap-align: start;
    flex: 0 0 calc(33.33% - 8px);
    min-width: 0;
}
@media (max-width: 900px) { .ccn-gallery-item { flex: 0 0 calc(50% - 6px); } }
@media (max-width: 576px) {
    .ccn-gallery-wrap-' . $iid . ' { padding: 0 32px; }
    .ccn-gallery-item { flex: 0 0 78%; }
}

/* thumbnail */
.ccn-gallery-thumb {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    aspect-ratio: 4 / 3;
    background: #111;
}
.ccn-gallery-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .3s ease;
}
.ccn-gallery-thumb:hover img { transform: scale(1.04); }

/* overlay */
.ccn-gallery-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,.45);
    opacity: 0;
    transition: opacity .25s;
}
.ccn-gallery-thumb:hover .ccn-gallery-overlay { opacity: 1; }
.ccn-gallery-overlay .flaticon-zoom-in {
    color: #fff;
    font-size: 32px;
}

/* nav arrows — identical style to featured video block */
.ccn-gallery-wrap-' . $iid . ' .ccn-vs-nav {
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
.ccn-gallery-wrap-' . $iid . ' .ccn-vs-nav:hover {
    background: #fff;
    transform: translateY(-50%) scale(1.1);
}
.ccn-gallery-wrap-' . $iid . ' .ccn-vs-prev { left: 2px; }
.ccn-gallery-wrap-' . $iid . ' .ccn-vs-next { right: 2px; }
</style>

<script>
function ccnGalleryScroll_' . $iid . '(dir) {
    var strip = document.getElementById("' . $strip_id . '");
    var step  = strip.clientWidth * 0.85;
    strip.scrollBy({ left: dir * step, behavior: "smooth" });
}
</script>';

        // ── Build image items ─────────────────────────────────────────────────
        $fs          = get_file_storage();
        $files       = $fs->get_area_files(
            $this->context->id, 'block_cocoon_gallery', 'content'
        );
        $items_html  = '';

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
            <div class="ccn-gallery-item">
              <div class="ccn-gallery-thumb">
                <img src="' . $url . '" alt="' . s($filename) . '">
                <div class="ccn-gallery-overlay">
                  <a href="' . $url . '" target="_blank" rel="noopener">
                    <span class="flaticon-zoom-in"></span>
                  </a>
                </div>
              </div>
            </div>';
        }

        // ── Build output ──────────────────────────────────────────────────────
        $this->content->text = '
<section class="about-section pb30 pt20">
  <div class="container">';

        // Title — only render if non-empty.
        if ($title !== '') {
            $this->content->text .= '
    <div class="row mb20">
      <div class="col-lg-6 offset-lg-3">
        <div class="main-title text-center">
          <h3 class="mt0" style="color:#C9A227;font-size:40px;">'
              . format_text($title, FORMAT_HTML, ['filter' => true]) . '</h3>';

            // Subtitle — only rendered if it also has content.
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

        // Images — show slider if there are images, or a placeholder message.
        if ($items_html !== '') {
            $this->content->text .= '
    <div class="ccn-gallery-wrap-' . $iid . '">
      <button class="ccn-vs-nav ccn-vs-prev"
              onclick="ccnGalleryScroll_' . $iid . '(-1)"
              aria-label="Previous">&#8250;</button>

      <div class="ccn-gallery-strip" id="' . $strip_id . '">
        ' . $items_html . '
      </div>

      <button class="ccn-vs-nav ccn-vs-next"
              onclick="ccnGalleryScroll_' . $iid . '(1)"
              aria-label="Next">&#8249;</button>
    </div>';
        }

        $this->content->text .= '
  </div>
</section>';

        return $this->content;
    }

    public function instance_allow_multiple()
    {
        return true;
    }

    function has_config()
    {
        return true;
    }

    function applicable_formats()
    {
        $ccnBlockHandler = new ccnBlockHandler();
        return $ccnBlockHandler->ccnGetBlockApplicability(['all']);
    }

    public function html_attributes()
    {
        global $CFG;
        $attributes = parent::html_attributes();
        include($CFG->dirroot . '/theme/edumy/ccn/block_handler/attributes.php');
        return $attributes;
    }
}
