@if(session('credential'))
    @php $cred = session('credential'); @endphp
    <div x-data="{ open: true, copied: false }" x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div @click.outside="open = false" class="glass-strong rounded-3xl max-w-md w-full p-6 border border-orange-500/40 space-y-4 rise">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="badge badge-orange mb-1">Kredensial Siap Kirim</span>
                    <h3 class="text-lg font-extrabold text-white">{{ $cred['name'] }}</h3>
                    <p class="text-xs text-slate-400">Simpan atau bagikan kredensial ini melalui WhatsApp.</p>
                </div>
                <button type="button" @click="open = false" class="text-slate-400 hover:text-white text-xl leading-none">&times;</button>
            </div>

            <div class="p-3.5 rounded-2xl bg-navy-950/80 border border-white/10 space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-slate-400">Email:</span>
                    <span class="font-mono font-bold text-white select-all">{{ $cred['email'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Password Baru:</span>
                    <span class="font-mono font-extrabold text-orange-400 text-sm select-all">{{ $cred['password'] }}</span>
                </div>
            </div>

            <div>
                <label class="label">Template Pesan WhatsApp</label>
                <textarea id="wa-credential-text" readonly rows="5" class="input font-mono text-xs">{{ $cred['message'] }}</textarea>
            </div>

            <div class="flex flex-wrap gap-2 pt-1">
                <button type="button"
                        @click="navigator.clipboard.writeText(document.getElementById('wa-credential-text').value); copied = true; window.HsmSwal?.toast('Template pesan disalin!')"
                        class="btn btn-primary flex-1">
                    <span x-text="copied ? 'Berhasil Disalin!' : 'Salin Template WA'">Salin Template WA</span>
                </button>
                @if(!empty($cred['wa_url']))
                    <a href="{{ $cred['wa_url'] }}" target="_blank" rel="noopener" class="btn btn-success">
                        Kirim ke WhatsApp
                    </a>
                @endif
                <button type="button" @click="open = false" class="btn btn-ghost">Tutup</button>
            </div>
        </div>
    </div>
@endif
