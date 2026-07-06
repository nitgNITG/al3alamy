<?php
global $CFG;
require_once($CFG->dirroot. '/theme/edumy/ccn/block_handler/ccn_block_handler.php');
require_once($CFG->dirroot. '/theme/edumy/ccn/general_handler/ccnLazy.php');

class block_cocoon_featured_video extends block_base
{
    public function init()
    {
        $this->title = get_string('pluginname', 'block_cocoon_featured_video');
    }

    public function specialization()
    {
        global $CFG, $DB;
        include($CFG->dirroot . '/theme/edumy/ccn/block_handler/specialization.php');
        if (empty($this->config)) {
            $this->config = new \stdClass();
            $this->config->videosnumber   = '1';
            $this->config->video_url      = 'https://youtu.be/UdDwKI4DcGw';
            $this->config->slidesnumber   = '4';
            $this->config->title1         = 'Creative Events';
            $this->config->subtitle1      = '749';
            $this->config->subtitle_2_1   = '';
            $this->config->title2         = 'Skilled Tutor';
            $this->config->subtitle2      = '832';
            $this->config->subtitle_2_2   = '';
            $this->config->title3         = 'Online Courses';
            $this->config->subtitle3      = '35';
            $this->config->subtitle_2_3   = 'k';
            $this->config->title4         = 'People Worldwide';
            $this->config->subtitle4      = '92';
            $this->config->subtitle_2_4   = 'k';
            $this->config->color_bfbg     = '#f9f9f9';
            $this->config->color_title    = '#0067da';
            $this->config->color_subtitle = '#222222';
            $this->config->color_overlay  = 'rgb(34, 34, 34, .4)';
        }
    }

    public function get_content()
    {
        global $CFG, $DB;
        require_once($CFG->libdir . '/filelib.php');

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass;

        // ── Colors ────────────────────────────────────────────────────────────
        $color_bfbg     = !empty($this->config->color_bfbg)     ? $this->config->color_bfbg     : '#f9f9f9';
        $color_title    = !empty($this->config->color_title)    ? $this->config->color_title    : '#0067da';
        $color_subtitle = !empty($this->config->color_subtitle) ? $this->config->color_subtitle : '#222222';
        $color_overlay  = !empty($this->config->color_overlay)  ? $this->config->color_overlay  : 'rgba(0,0,0,0.45)';

        // ── Counter data ──────────────────────────────────────────────────────
        $data = new stdClass();
        if (!empty($this->config) && is_object($this->config)) {
            $data = $this->config;
            $data->slidesnumber = isset($data->slidesnumber) && is_numeric($data->slidesnumber)
                ? (int)$data->slidesnumber : 4;
        } else {
            $data->slidesnumber = 4;
        }

        // ── Collect uploaded images sorted by filename ────────────────────────
        $default_image   = $CFG->wwwroot . '/theme/edumy/images/ccnBgMd.png';
        $uploaded_images = [];
        $fs    = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'block_cocoon_featured_video', 'content');
        usort($files, function($a, $b) {
            return strcmp($a->get_filename(), $b->get_filename());
        });
        foreach ($files as $file) {
            if ($file->get_filename() !== '.') {
                $uploaded_images[] = (string) moodle_url::make_pluginfile_url(
                    $file->get_contextid(), $file->get_component(),
                    $file->get_filearea(), null,
                    $file->get_filepath(), $file->get_filename()
                );
            }
        }
        $slot1_image          = !empty($uploaded_images[0]) ? $uploaded_images[0] : $default_image;
        $this->content->image = $slot1_image;

        // ── Number of videos ──────────────────────────────────────────────────
        $videosnumber = !empty($this->config->videosnumber) ? (int)$this->config->videosnumber : 1;
        $videosnumber = max(1, min(8, $videosnumber));

        // ── Build slides ──────────────────────────────────────────────────────
        $slides = [];
        for ($i = 1; $i <= $videosnumber; $i++) {
            $video_url = '';
            if (!empty($this->config->{'video_url_' . $i})) {
                $video_url = $this->config->{'video_url_' . $i};
            } elseif ($i === 1 && !empty($this->config->video_url)) {
                $video_url = $this->config->video_url;
            }
            $image_url = !empty($uploaded_images[$i - 1]) ? $uploaded_images[$i - 1] : $slot1_image;
            $slides[]  = ['video_url' => $video_url, 'image_url' => $image_url];
        }

        // ── Unique IDs ────────────────────────────────────────────────────────
        $iid      = $this->instance->id;
        $modal_id = 'ccnVM_'  . $iid;
        $strip_id = 'ccnVS_'  . $iid;
        $frame_id = 'ccnVF_'  . $iid;
        $fn_name  = 'ccnVPlay_' . $iid;

        // ── Build thumbnail strip ─────────────────────────────────────────────
        $items_html = '';
        foreach ($slides as $slide) {
            $vurl = htmlspecialchars($slide['video_url'], ENT_QUOTES);
            $iurl = htmlspecialchars($slide['image_url']);
            $items_html .= '
            <div class="ccn-vs-item">
                <div class="ccn-vs-thumb" onclick="' . $fn_name . '(\'' . $vurl . '\')">
                    <img src="' . $iurl . '" alt="" loading="lazy">
                    <div class="ccn-vs-overlay">
                        <div class="ccn-vs-play"><span class="flaticon-play-button-1"></span></div>
                    </div>
                </div>
            </div>';
        }

        // ── Modal (compact) ───────────────────────────────────────────────────
        $modal_html = '
        <div class="modal fade ccn-video-modal" id="' . $modal_id . '" tabindex="-1"
             role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document"
                 style="max-width:560px;width:92%;">
                <div class="modal-content" style="background:#000;border:none;border-radius:8px;overflow:hidden;">
                    <div class="modal-header"
                         style="border:none;padding:6px 10px;justify-content:flex-end;">
                        <button type="button" class="close" data-dismiss="modal"
                                onclick="document.getElementById(\'' . $frame_id . '\').src=\'\';"
                                style="color:#fff;opacity:.8;font-size:24px;line-height:1;">
                            &times;
                        </button>
                    </div>
                    <div class="modal-body" style="padding:0;">
                        <div class="embed-responsive embed-responsive-16by9">
                            <iframe id="' . $frame_id . '"
                                    class="embed-responsive-item"
                                    src="" frameborder="0"
                                    allow="autoplay; encrypted-media; picture-in-picture"
                                    allowfullscreen></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>';

        // ── CSS + JS ──────────────────────────────────────────────────────────
        $css_js = '
        <style>
        /* ── video strip ── */
        .ccn-vs-wrap {
            position: relative;
            padding: 0 40px;
        }
        .ccn-vs-strip {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            padding-bottom: 6px;
        }
        .ccn-vs-strip::-webkit-scrollbar { display: none; }

        /* desktop: 3 visible + hint of 4th */
        .ccn-vs-item {
            scroll-snap-align: start;
            flex: 0 0 calc(33.33% - 8px);
            min-width: 0;
        }
        /* tablet: 2 visible */
        @media (max-width: 900px) {
            .ccn-vs-item { flex: 0 0 calc(50% - 6px); }
        }
        /* mobile: 1.3 visible */
        @media (max-width: 576px) {
            .ccn-vs-wrap { padding: 0 32px; }
            .ccn-vs-item { flex: 0 0 78%; }
        }

        /* thumbnail */
        .ccn-vs-thumb {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            aspect-ratio: 16/9;
            background: #111;
        }
        .ccn-vs-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform .3s ease;
        }
        .ccn-vs-thumb:hover img { transform: scale(1.04); }

        /* overlay */
        .ccn-vs-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,.35);
            transition: background .25s;
        }
        .ccn-vs-thumb:hover .ccn-vs-overlay { background: rgba(0,0,0,.55); }

        /* play button */
        .ccn-vs-play {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: rgba(255,255,255,.92);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform .2s, box-shadow .2s;
            box-shadow: 0 2px 12px rgba(0,0,0,.4);
        }
        .ccn-vs-thumb:hover .ccn-vs-play {
            transform: scale(1.12);
            box-shadow: 0 4px 20px rgba(0,0,0,.5);
        }
        .ccn-vs-play .flaticon-play-button-1 {
            font-size: 20px;
            color: #222;
            margin-left: 3px;
        }
        @media (max-width: 576px) {
            .ccn-vs-play { width: 42px; height: 42px; }
            .ccn-vs-play .flaticon-play-button-1 { font-size: 16px; }
        }

        /* nav arrows */
        .ccn-vs-nav {
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
        .ccn-vs-nav:hover { background: #fff; transform: translateY(-50%) scale(1.1); }
        .ccn-vs-prev { left: 2px; }
        .ccn-vs-next { right: 2px; }
        </style>

        <script>
        function ' . $fn_name . '(url) {
            // Convert any YouTube URL → embed with autoplay
            function toEmbed(u) {
                if (!u) return "";
                if (u.indexOf("youtube.com/embed/") !== -1)
                    return u + (u.indexOf("?") !== -1 ? "&" : "?") + "autoplay=1";
                var m = u.match(/youtu\.be\/([^?&\s"]+)/);
                if (m) return "https://www.youtube.com/embed/" + m[1] + "?autoplay=1";
                m = u.match(/[?&]v=([^?&\s"]+)/);
                if (m) return "https://www.youtube.com/embed/" + m[1] + "?autoplay=1";
                return u;
            }
            document.getElementById("' . $frame_id . '").src = toEmbed(url);
            if (typeof jQuery !== "undefined") {
                jQuery("#' . $modal_id . '").modal("show");
            }
        }

        // Scroll strip left/right by one page width
        function ccnVsScroll_' . $iid . '(dir) {
            var strip = document.getElementById("' . $strip_id . '");
            var step  = strip.clientWidth * 0.85;
            strip.scrollBy({ left: dir * step, behavior: "smooth" });
        }

        // Stop video on close — three redundant paths so one always fires:
        (function() {
            function ccnClearFrame() {
                document.getElementById("' . $frame_id . '").src = "";
            }

            // 1. Backdrop click (user clicks outside modal box)
            var modalEl = document.getElementById("' . $modal_id . '");
            if (modalEl) {
                modalEl.addEventListener("click", function(e) {
                    if (e.target === modalEl) ccnClearFrame();
                });
            }

            // 2. Escape key
            document.addEventListener("keydown", function(e) {
                if ((e.key === "Escape" || e.keyCode === 27) && modalEl &&
                    modalEl.classList.contains("show")) {
                    ccnClearFrame();
                }
            });

            // 3. Bootstrap hide event (jQuery BS4 or native BS5)
            (function waitJQ() {
                if (typeof jQuery !== "undefined") {
                    jQuery("#' . $modal_id . '").on("hide.bs.modal", ccnClearFrame);
                } else { setTimeout(waitJQ, 80); }
            })();
        })();
        </script>';

        // ── Full section ──────────────────────────────────────────────────────
        $this->content->text = $modal_html . $css_js . '
        <section class="about-us-home13 pb20 pt20"
                 style="background-color:' . htmlspecialchars($color_bfbg) . ';">
          <div class="container">
            <div class="ccn-vs-wrap">
              <button class="ccn-vs-nav ccn-vs-prev"
                      onclick="ccnVsScroll_' . $iid . '(-1)"
                      aria-label="Previous">&#8249;</button>

              <div class="ccn-vs-strip" id="' . $strip_id . '">
                ' . $items_html . '
              </div>

              <button class="ccn-vs-nav ccn-vs-next"
                      onclick="ccnVsScroll_' . $iid . '(1)"
                      aria-label="Next">&#8250;</button>
            </div>
          </div>
        </section>';

        // ── Counter section ───────────────────────────────────────────────────
        if ($data->slidesnumber > 0) {
            $this->content->text .= '
        <section class="our-courses pt0 pb0">
          <div class="container pb60">
            <div class="row">';
            for ($i = 1; $i <= $data->slidesnumber; $i++) {
                $title     = 'title' . $i;
                $subtitle  = 'subtitle' . $i;
                $subtitle2 = 'subtitle_2_' . $i;
                $this->content->text .= '
              <div class="col-sm-6 col-lg-3">
                <div class="funfact_one home13 text-center">
                  <div class="details">
                    <ul>
                      <li class="list-inline-item">
                        <div class="timer"
                             data-ccn="' . $subtitle . '"
                             data-ccn-c="color_title" data-ccn-co="ccnCn"
                             data-ccn-cv="' . htmlspecialchars($color_title) . '">'
                             . (isset($data->$subtitle) ? $data->$subtitle : '') . '</div>
                      </li>
                      <li class="list-inline-item">
                        <span data-ccn="' . $subtitle2 . '"
                              data-ccn-c="color_title" data-ccn-co="ccnCn"
                              data-ccn-cv="' . htmlspecialchars($color_title) . '">'
                              . (isset($data->$subtitle2) ? $data->$subtitle2 : '') . '</span>
                      </li>
                    </ul>
                    <h5 data-ccn="' . $title . '"
                        data-ccn-c="color_subtitle" data-ccn-co="ccnCn"
                        data-ccn-cv="' . htmlspecialchars($color_subtitle) . '">'
                        . (isset($data->$title) ? $data->$title : '') . '</h5>
                  </div>
                </div>
              </div>';
            }
            $this->content->text .= '
            </div>
          </div>
        </section>';
        }

        return $this->content;
    }

    public function instance_allow_multiple() { return true; }
    function has_config() { return true; }

    function applicable_formats() {
        $ccnBlockHandler = new ccnBlockHandler();
        return $ccnBlockHandler->ccnGetBlockApplicability(array('all'));
    }

    public function html_attributes() {
        global $CFG;
        $attributes = parent::html_attributes();
        include($CFG->dirroot . '/theme/edumy/ccn/block_handler/attributes.php');
        return $attributes;
    }
}
