/**
 * Drag-drop upload with XHR progress and status polling.
 */
document.addEventListener('alpine:init', () => {
  Alpine.data('uploadPage', () => ({
    file: null,
    fileName: '',
    dragover: false,
    uploading: false,
    progress: 0,
    statusText: '',
    pollTimer: null,
    fields: {
      title: '',
      contact_name: '',
      contact_role: '',
      contact_tenure: '',
      call_date: typeof window.__CALL_DATE_DEFAULT === 'string' ? window.__CALL_DATE_DEFAULT : '',
      whisper_language: '',
      agent_user_id: String(window.__CURRENT_USER_ID || ''),
    },
    onDrop(e) {
      this.dragover = false;
      const f = e.dataTransfer.files[0];
      if (f) this.setFile(f);
    },
    onFile(e) {
      const f = e.target.files[0];
      if (f) this.setFile(f);
    },
    setFile(f) {
      this.file = f;
      this.fileName = f.name;
    },
    async pollCallStatus(callId) {
      const statusEl = document.getElementById('status-message');
      const statuses = {
        uploaded: 'File received. Starting transcription...',
        transcribing: '🎙️ Transcribing audio with Whisper AI...',
        analyzing: '🤖 Running GPT-4o analysis...',
        complete: '✅ Analysis complete! Redirecting...',
        failed: '❌ Processing failed.',
      };
      if (this.pollTimer) clearInterval(this.pollTimer);
      this.pollTimer = setInterval(async () => {
        try {
          const res = await fetch(appUrl('/api/calls/' + callId + '/status'), { headers: { Accept: 'application/json' } });
          const data = await res.json();
          let msg = statuses[data.status] || 'Processing...';
          if (data.status === 'failed' && data.error) {
            msg = '❌ ' + data.error;
          } else if (data.status === 'failed') {
            msg = statuses.failed + ' See storage/logs/app.log';
          }
          this.statusText = msg;
          if (statusEl) statusEl.textContent = msg;
          if (data.status === 'complete') {
            clearInterval(this.pollTimer);
            window.location.href = appUrl('/calls/' + callId);
          }
          if (data.status === 'failed') {
            clearInterval(this.pollTimer);
          }
        } catch {
          this.statusText = 'Checking status...';
        }
      }, 3000);
    },
    submitUpload() {
      if (!this.file || this.uploading) return;
      this.uploading = true;
      this.progress = 0;
      this.statusText = 'Uploading...';
      const fd = new FormData();
      fd.append('_csrf', typeof readCsrfToken === 'function' ? readCsrfToken() : getCsrfToken());
      fd.append('xhr', '1');
      fd.append('title', this.fields.title);
      fd.append('contact_name', this.fields.contact_name);
      fd.append('contact_role', this.fields.contact_role);
      fd.append('contact_tenure', this.fields.contact_tenure);
      fd.append('call_date', this.fields.call_date);
      fd.append('whisper_language', this.fields.whisper_language || '');
      fd.append('agent_user_id', this.fields.agent_user_id);
      fd.append('audio', this.file);

      const xhr = new XMLHttpRequest();
      xhr.open('POST', appUrl('/calls/upload'));
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      const csrf = typeof readCsrfToken === 'function' ? readCsrfToken() : getCsrfToken();
      if (csrf) {
        xhr.setRequestHeader('X-CSRF-Token', csrf);
      }
      xhr.upload.onprogress = (ev) => {
        if (ev.lengthComputable) this.progress = Math.round((ev.loaded / ev.total) * 100);
      };
      xhr.onload = () => {
        this.uploading = false;
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            const data = JSON.parse(xhr.responseText);
            if (data.call_id) {
              this.progress = 100;
              this.pollCallStatus(data.call_id);
            }
          } catch {
            this.statusText = 'Unexpected response';
          }
        } else {
          try {
            const err = JSON.parse(xhr.responseText);
            this.statusText = err.error || 'Upload failed (HTTP ' + xhr.status + ')';
          } catch {
            const snippet = (xhr.responseText || '').replace(/<[^>]+>/g, ' ').trim().slice(0, 200);
            this.statusText = snippet || 'Upload failed (HTTP ' + xhr.status + ')';
          }
        }
      };
      xhr.onerror = () => {
        this.uploading = false;
        this.statusText = 'Network error';
      };
      xhr.send(fd);
    },
  }));
});
