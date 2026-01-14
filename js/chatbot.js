((Drupal, drupalSettings) => {
  Drupal.behaviors.frontend = {
    attach(context) {

      const thread_id = Math.random().toString(36).substring(7);
      const chatElementRef = document.getElementById('chat-element');
      chatElementRef.connect = {
        stream: {
          partialRender: true,
        },
        handler: (body, signals) => {
          const encodedQuery = body.messages[0].text;

          try {
            const evtSource = new EventSource('/ai-react-agent/react?objective=' + encodedQuery + '&agent_id=drupal_cms_agent&thread_id=' + thread_id);

            evtSource.onmessage = (event) => {
              signals.onResponse({ text: event.data, role: 'ai' });
            };

            evtSource.addEventListener('close', () => {
              evtSource.close();
              signals.onClose();
            });

            evtSource.addEventListener('tool', (event) => {
              signals.onResponse({ text: event.data, role: 'tool' });
            });
          } catch (e) {
            signals.onResponse({ error: 'Error' });
          }
        },
      };
      chatElementRef.responseInterceptor = (response) => {
        if (response.role === 'tool') {
          // Wrap tool responses in new lines for better readability.
          // @todo: Tool response should be in its own chat bubble.
          return { text: "\n\n"+response.text+"\n\n", role: 'ai' };
        }

        return response;
      };

    },
  };
})
(Drupal, drupalSettings);
