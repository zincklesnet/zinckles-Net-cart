(function($){
    var nonce = (typeof zncAdmin !== 'undefined') ? zncAdmin.nonce : '';
    var url = (typeof zncAdmin !== 'undefined') ? zncAdmin.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');

    function showMsg(el, msg, type) {
        var cls = type === 'error' ? 'notice-error' : 'notice-success';
        $(el).before('<div class="notice ' + cls + ' is-dismissible" style="margin:10px 0"><p>' + msg + '</p></div>');
        setTimeout(function(){ $('.notice.is-dismissible').fadeOut(); }, 4000);
    }

    // Save settings
    $(document).on('submit', '#znc-settings-form', function(e){
        e.preventDefault();
        var form = $(this), btn = form.find('[type=submit]');
        btn.prop('disabled', true).text('Saving...');
        var data = form.serialize();
        data += '&action=znc_save_settings&nonce=' + nonce;
        $.post(url, data, function(r){
            showMsg(form, r.success ? 'Settings saved successfully!' : 'Error: ' + (r.data || 'Unknown'), r.success ? 'success' : 'error');
            btn.prop('disabled', false).text('Save Settings');
        }).fail(function(x){ showMsg(form, 'AJAX Error: ' + x.status + ' ' + x.statusText, 'error'); btn.prop('disabled', false).text('Save Settings'); });
    });

    // Enroll
    $(document).on('click', '.znc-enroll-btn', function(){
        var btn = $(this), bid = btn.data('blog-id');
        btn.prop('disabled', true).text('Enrolling...');
        $.post(url, {action:'znc_enroll_site', blog_id:bid, nonce:nonce}, function(r){
            if (r.success) {
                btn.closest('tr').find('.znc-status-badge').text('Enrolled').removeClass('znc-not-enrolled').addClass('znc-enrolled');
                btn.parent().html('<button class="button znc-remove-btn" data-blog-id="'+bid+'">Remove</button> <button class="button znc-test-btn" data-blog-id="'+bid+'">Test</button>');
            } else {
                alert('Error: ' + (r.data || 'Unknown'));
                btn.prop('disabled', false).text('Enroll');
            }
        }).fail(function(x){ alert('AJAX Error: ' + x.status + ' — ' + x.statusText); btn.prop('disabled', false).text('Enroll'); });
    });

    // Remove
    $(document).on('click', '.znc-remove-btn', function(){
        var btn = $(this), bid = btn.data('blog-id');
        if (!confirm('Remove this site from Net Cart?')) return;
        btn.prop('disabled', true).text('Removing...');
        $.post(url, {action:'znc_remove_site', blog_id:bid, nonce:nonce}, function(r){
            if (r.success) {
                btn.closest('tr').find('.znc-status-badge').text('Not Enrolled').removeClass('znc-enrolled').addClass('znc-not-enrolled');
                btn.parent().html('<button class="button button-primary znc-enroll-btn" data-blog-id="'+bid+'">Enroll</button>');
            }
        }).fail(function(x){ alert('AJAX Error: ' + x.status); btn.prop('disabled', false).text('Remove'); });
    });

    // Test connection
    $(document).on('click', '.znc-test-btn', function(){
        var btn = $(this), bid = btn.data('blog-id');
        btn.prop('disabled', true).text('Testing...');
        $.post(url, {action:'znc_test_connection', blog_id:bid, nonce:nonce}, function(r){
            if (r.success) {
                var msg = 'Connection OK!\n';
                msg += 'Site: ' + r.data.name + '\n';
                msg += 'WooCommerce: ' + (r.data.has_wc ? 'Yes' : 'No') + '\n';
                msg += 'Tutor LMS: ' + (r.data.has_tutor ? 'Yes' : 'No');
                alert(msg);
            } else { alert('Test failed: ' + (r.data || 'Unknown')); }
            btn.prop('disabled', false).text('Test');
        }).fail(function(x){ alert('AJAX Error: ' + x.status); btn.prop('disabled', false).text('Test'); });
    });

    // Save security
    $(document).on('submit', '#znc-security-form', function(e){
        e.preventDefault();
        var form = $(this), btn = form.find('[type=submit]');
        btn.prop('disabled', true).text('Saving...');
        var data = form.serialize();
        data += '&action=znc_save_security&nonce=' + nonce;
        $.post(url, data, function(r){
            showMsg(form, r.success ? 'Security settings saved!' : 'Error: ' + (r.data || 'Unknown'), r.success ? 'success' : 'error');
            btn.prop('disabled', false).text('Save Security Settings');
        }).fail(function(x){ showMsg(form, 'AJAX Error: ' + x.status + ' ' + x.statusText, 'error'); btn.prop('disabled', false).text('Save Security Settings'); });
    });

    // Regenerate secret
    $(document).on('click', '#znc-regenerate-secret', function(){
        if (!confirm('Regenerate HMAC secret? All existing tokens will be invalidated.')) return;
        var btn = $(this);
        btn.prop('disabled', true).text('Generating...');
        $.post(url, {action:'znc_regenerate_secret', nonce:nonce}, function(r){
            if (r.success) {
                $('#znc-hmac-display').text(r.data.secret.substring(0,24) + '...');
                showMsg(btn.closest('table'), 'HMAC secret regenerated! Generated at: ' + r.data.generated_at, 'success');
            } else { alert('Error: ' + (r.data || 'Unknown')); }
            btn.prop('disabled', false).text('Regenerate Secret');
        }).fail(function(x){ alert('AJAX Error: ' + x.status); btn.prop('disabled', false).text('Regenerate Secret'); });
    });

    // Detect point types
    $(document).on('click', '#znc-detect-points', function(){
        var btn = $(this);
        btn.prop('disabled', true).text('Scanning all sites...');
        $.post(url, {action:'znc_detect_point_types', nonce:nonce}, function(r){
            if (r.success) {
                var mc = r.data.mycred || {}, gp = r.data.gamipress || {}, tu = r.data.tutor || {};
                var html = '<div class="notice notice-success" style="margin:10px 0;padding:12px"><strong>Detection Complete:</strong><br>';
                html += 'MyCred: ' + (Object.keys(mc).length ? Object.values(mc).join(', ') : 'None found') + '<br>';
                html += 'GamiPress: ' + (Object.keys(gp).length ? Object.values(gp).join(', ') : 'None found') + '<br>';
                html += 'Tutor LMS: ' + (Object.keys(tu).length ? Object.keys(tu).length + ' site(s)' : 'None found');
                html += '</div>';

                var mh = '';
                for (var k in mc) mh += '<label><input type="checkbox" name="mycred_types[]" value="'+k+'" checked> '+mc[k]+'</label><br>';
                var gh = '';
                for (var k in gp) gh += '<label><input type="checkbox" name="gamipress_types[]" value="'+k+'" checked> '+gp[k]+'</label><br>';
                var th = '';
                for (var bid in tu) th += '<span class="znc-tag">' + tu[bid].name + ' (' + tu[bid].courses + ' courses)</span> ';

                $('#znc-mycred-types').html(mh || '<em>None found on enrolled sites</em>');
                $('#znc-gamipress-types').html(gh || '<em>None found on enrolled sites</em>');
                $('#znc-tutor-sites').html(th || '<em>None found</em>');
                $('#znc-detected-points').html(html);
            } else { alert('Detection failed: ' + (r.data || 'Unknown')); }
            btn.prop('disabled', false).text('\uD83D\uDD0D Auto-Detect Point Types');
        }).fail(function(x){ alert('AJAX Error: ' + x.status + ' — ' + x.statusText); btn.prop('disabled', false).text('\uD83D\uDD0D Auto-Detect Point Types'); });
    });
})(jQuery);
