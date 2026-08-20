<!DOCTYPE html>
<html lang="tr" data-bs-theme="dark" data-image-fallback="{{ asset('assets/media/placeholder.svg') }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('assets/media/logos/favicon.svg') }}" type="image/x-icon" />

    <script>
        (function () {
            var t = localStorage.getItem('theme') || 'dark';
            var d = document.documentElement;
            d.classList.remove('light-mode', 'dark-mode');
            d.classList.add(t + '-mode');
            d.setAttribute('data-bs-theme', t === 'dark' ? 'dark' : 'light');
            d.classList.add('loaded');
        })();
    </script>

    <link href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/style.bundle.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/theme-new.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/auth.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/story-upload.css') }}" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    @routes
    @vite(['resources/js/app.js'])
    @inertiaHead
</head>

<body id="kt_app_body" data-kt-app-layout="dark-sidebar" data-kt-app-header-fixed="true" data-kt-app-sidebar-enabled="true" data-kt-app-sidebar-fixed="true" data-kt-app-sidebar-hoverable="true" data-kt-app-sidebar-push-header="true" data-kt-app-sidebar-push-toolbar="true" data-kt-app-sidebar-push-footer="true">

    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('assets/js/scripts.bundle.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="{{ asset('assets/js/custom/theme/image-fallback.js') }}"></script>
    <script src="{{ asset('assets/js/custom/theme/ajax-delete.js') }}"></script>

    {{-- Hikaye (story) modalları — Vue dışında, kalıcı DOM. story-viewer.js/upload.js bunlara bağlanır --}}
    @auth
    <div class="story-viewer" id="storyViewer" data-testid="story-viewer">
        <div class="story-viewer-backdrop" onclick="closeStoryViewer()"></div>
        <div class="story-viewer-stage">
            <div class="story-progress" id="storyProgress"></div>
            <div class="story-viewer-head">
                <div class="story-viewer-user">
                    <img id="svAvatar" src="" alt="">
                    <span id="svName"></span>
                </div>
                <div class="story-viewer-actions">
                    <button class="story-viewer-del" id="svDelete" onclick="deleteCurrentStory()" data-testid="story-delete" title="Hikayeyi sil" style="display:none"><i class="bi bi-trash"></i></button>
                    <button class="story-viewer-close" onclick="closeStoryViewer()" data-testid="story-close"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="story-viewer-media" id="svMedia"></div>
            <div class="story-viewer-caption" id="svCaption"></div>
            <button class="story-nav story-prev" onclick="storyPrev()"><i class="bi bi-chevron-left"></i></button>
            <button class="story-nav story-next" onclick="storyNext()"><i class="bi bi-chevron-right"></i></button>
        </div>
    </div>
    <form id="storyDeleteForm" method="POST" style="display:none">@csrf @method('DELETE')</form>

    @if(auth()->user()->isSeller())
    <div class="story-upload-overlay" id="storyUploadModal" data-testid="story-upload-modal" hidden>
        <div class="story-upload-box">
            <div class="su-head">
                <span>Hikaye Paylaş</span>
                <button type="button" onclick="closeStoryUpload()" data-testid="story-upload-close"><i class="bi bi-x-lg"></i></button>
            </div>
            <form action="{{ route('stories.store') }}" method="POST" enctype="multipart/form-data" id="storyUploadForm">
                @csrf
                <label class="su-drop" id="suDrop">
                    <input type="file" name="media" id="storyFileInput" accept="image/*,video/*" hidden data-testid="story-file-input">
                    <div id="suPlaceholder" class="su-ph">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <span>Görsel veya video seç</span>
                        <small>JPG, PNG, MP4 · max 20MB</small>
                    </div>
                    <div id="suPreview" class="su-preview"></div>
                </label>
                <input type="text" name="caption" maxlength="150" class="su-caption" placeholder="Açıklama (isteğe bağlı)">
                <button type="submit" class="su-submit" id="suSubmit" disabled data-testid="story-upload-submit"><i class="bi bi-send me-1"></i> Paylaş</button>
            </form>
        </div>
    </div>
    <script src="{{ asset('assets/js/custom/story-upload.js') }}"></script>
    @endif
    <script src="{{ asset('assets/js/custom/story-viewer.js') }}"></script>
    @endauth

    @inertia
</body>

</html>
