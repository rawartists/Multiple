<script type="text/javascript">
    $(document).ready(function () {


        $('.checkbox_header').click(function(e){

            e.preventDefault();

            if($(this).data('header')) {

                var header = $(this).data('header');
                var type = $(this).data('type');
                type = (type == 'all');

                $("input[data-header='" + header + "']").prop('checked', type);

            }

            if($(this).data('section')) {

                var section = $(this).data('section');
                var type = $(this).data('type');
                type = (type == 'all');

                $("input[data-section='" + section + "']").prop('checked', type);

            }

        });


    });
</script>
