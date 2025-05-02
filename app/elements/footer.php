<a id="change_password">Change Password</a>
<p>See this Project on <a href="https://github.com/yniverz/php-uni-support">GitHub</a></p>
<p>Created by <a href="https://github.com/yniverz">yniverz</a></p>
<p><?php echo htmlspecialchars(file_get_contents(__DIR__ . '/../../VERSION')); ?></p>


<script>
    // when change_password is clicked, show prompt for new password and then send to server
    document.getElementById('change_password').addEventListener('click', function () {
        const newPassword = prompt("Enter new password:");
        if (newPassword) {
            const form = document.createElement('form');
            form.method = 'post';
            form.action = '';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'change_password';
            input.value = newPassword;
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    });
</script>